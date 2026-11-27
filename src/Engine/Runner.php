<?php

namespace MightyMango\Flowkit\Engine;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Condition;
use MightyMango\Flowkit\Bricks\Core\Branch;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Events\RunFinished;
use MightyMango\Flowkit\Events\RunStarted;
use MightyMango\Flowkit\Events\StepFinished;
use MightyMango\Flowkit\Jobs\RunFlow;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\Models\FlowRunStep;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Throwable;

/**
 * Walks a version's step list against a context, one step at a time,
 * recording every step it takes in flow_run_steps.
 *
 * A run can be executed more than once: after an automatic retry, or when
 * a failed run is retried by hand. Steps that already completed are not
 * run again; the walk follows their recorded outputs (including which
 * branch was taken) and picks up at the step that failed, as a new attempt.
 */
class Runner
{
    /**
     * Internal signal from a failed standalone condition: stop walking and
     * finish the run as completed. Never stored.
     */
    private const STOPPED = 'stopped';

    /**
     * Internal signal from a step that threw and can be tried again: stop
     * walking and queue the run to resume after a backoff. Never stored.
     */
    private const RETRY = 'retry';

    public function __construct(
        private BrickRegistry $bricks,
        private Expression $expression,
    ) {}

    public function execute(FlowRun $run): FlowRun
    {
        if ($run->status?->isFinished()) {
            return $run;
        }

        $definition = $run->version->toDefinition();
        $context = new Context(
            array_replace_recursive($run->payload ?? [], $run->context ?? []),
            runId: $run->id,
            dryRun: $run->dry_run,
        );

        // The latest attempt at each step this run has already taken.
        $history = $run->steps()->get()->keyBy('step_id')->all();

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => $run->started_at ?? now()])->save();

        RunStarted::dispatch($run->id, $run->flow_version_id, $run->correlation_id, (bool) $run->dry_run, $history !== []);

        $retry = null;
        $outcome = $this->walk($definition->steps, $context, $run, $history, $retry);

        $outcome = match ($outcome) {
            self::STOPPED => RunStatus::Completed,
            self::RETRY => RunStatus::Pending,
            default => $outcome,
        };

        $run->forceFill([
            'status' => $outcome,
            'context' => $context->all(),
            'finished_at' => $outcome->isFinished() ? now() : null,
        ])->save();

        if ($outcome->isFinished()) {
            RunFinished::dispatch($run->id, $run->flow_version_id, $run->correlation_id, $outcome, (bool) $run->dry_run);
        }

        if ($retry !== null) {
            RunFlow::dispatch($run)->delay(now()->addSeconds($retry));
        }

        return $run;
    }

    /**
     * Run a list of steps in order. Stops early if a step waits, fails or
     * will be retried, or a standalone condition fails.
     *
     * @param  array<string, FlowRunStep>  $history
     */
    private function walk(array $steps, Context $context, FlowRun $run, array $history, ?int &$retry): RunStatus|string
    {
        foreach ($steps as $step) {
            $status = $this->runStep($step, $context, $run, $history, $retry);

            if (in_array($status, [self::STOPPED, self::RETRY, RunStatus::Waiting, RunStatus::Failed], true)) {
                return $status;
            }
        }

        return RunStatus::Completed;
    }

    /**
     * @param  array<string, FlowRunStep>  $history
     */
    private function runStep(array $step, Context $context, FlowRun $run, array $history, ?int &$retry): RunStatus|string
    {
        $previous = $history[$step['id']] ?? null;

        // Already done on an earlier execution of this run: don't repeat it.
        // Its output is already in the run's context.
        if ($previous && in_array($previous->status, [RunStatus::Completed, RunStatus::Skipped], true)) {
            if (isset($previous->output['branch'])) {
                return $this->walk($step['branches'][$previous->output['branch']] ?? [], $context, $run, $history, $retry);
            }

            return ($previous->output['stopped'] ?? false) ? self::STOPPED : $previous->status;
        }

        $started = hrtime(true);
        $record = new FlowRunStep([
            'step_id' => $step['id'],
            'brick' => $step['brick'],
            'status' => RunStatus::Running,
            'attempt' => ($previous?->attempt ?? 0) + 1,
        ]);
        $record->run()->associate($run);
        $brick = null;
        $handled = false;

        try {
            $brick = $this->bricks->resolve($step['brick']);
            $settings = $this->expression->resolve($step['settings'] ?? [], $context);
            $record->input = $settings;

            $validator = Validator::make($settings, $brick->rulesFor($settings));
            if ($validator->fails()) {
                return $this->finish($record, $started, RunStatus::Failed, error: implode(' ', $validator->errors()->all()));
            }

            if ($brick instanceof Branch) {
                $handled = true;
                $chosen = $brick->decide($context, $settings);
                $this->finish($record, $started, RunStatus::Completed, ['branch' => $chosen], handled: true);
                $context->recordOutput($step['id'], ['branch' => $chosen]);

                return $this->walk($step['branches'][$chosen] ?? [], $context, $run, $history, $retry);
            }

            // A condition on its own is a filter: if it fails, the run stops
            // here and completes without running the steps after it.
            if ($brick instanceof Condition) {
                $handled = true;
                $passed = $brick->check($context, $settings);
                $output = $passed ? ['passed' => true] : ['passed' => false, 'stopped' => true];
                $context->recordOutput($step['id'], $output);
                $this->finish($record, $started, RunStatus::Completed, $output, handled: true);

                return $passed ? RunStatus::Completed : self::STOPPED;
            }

            if (! $brick instanceof Action) {
                throw new LogicException("Brick [{$step['brick']}] can't be used as a step.");
            }

            $context->forStep($step['id']);

            $handled = ! ($context->dryRun && ! $brick::$retrySafe);

            $result = $handled
                ? $brick->handle($context, $settings)
                : $brick->dryRun($context, $settings);

            $context->recordOutput($step['id'], $result->output);

            return $this->finish($record, $started, $result->status, $result->output, $result->error, $handled);
        } catch (Throwable $e) {
            report($e);

            $this->finish($record, $started, RunStatus::Failed, error: $e->getMessage(), handled: $handled);

            if ($brick instanceof Action && $record->attempt < $brick->tries()) {
                $retry = $brick->backoff($record->attempt);

                return self::RETRY;
            }

            return RunStatus::Failed;
        }
    }

    private function finish(FlowRunStep $record, int $started, RunStatus $status, array $output = [], ?string $error = null, bool $handled = false): RunStatus
    {
        $record->fill([
            'status' => $status,
            'output' => $output,
            'error' => $error,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ])->save();

        StepFinished::dispatch($record->flow_run_id, $record->id, $record->step_id, $record->brick, $status, $record->attempt, $handled);

        return $status;
    }
}
