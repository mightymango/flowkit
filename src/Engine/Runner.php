<?php

namespace MightyMango\Flowkit\Engine;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Condition;
use MightyMango\Flowkit\Bricks\Core\Branch;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\Models\FlowRunStep;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Throwable;

/**
 * Walks a version's step list against a context, one step at a time,
 * recording every step it takes in flow_run_steps.
 */
class Runner
{
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

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => $run->started_at ?? now()])->save();

        $outcome = $this->walk($definition->steps, $context, $run);

        $run->forceFill([
            'status' => $outcome,
            'context' => $context->all(),
            'finished_at' => $outcome === RunStatus::Waiting ? null : now(),
        ])->save();

        return $run;
    }

    /**
     * Run a list of steps in order. Stops early if a step waits or fails.
     */
    private function walk(array $steps, Context $context, FlowRun $run): RunStatus
    {
        foreach ($steps as $step) {
            $status = $this->runStep($step, $context, $run);

            if ($status === RunStatus::Waiting || $status === RunStatus::Failed) {
                return $status;
            }
        }

        return RunStatus::Completed;
    }

    private function runStep(array $step, Context $context, FlowRun $run): RunStatus
    {
        $started = hrtime(true);
        $record = new FlowRunStep([
            'step_id' => $step['id'],
            'brick' => $step['brick'],
            'status' => RunStatus::Running,
        ]);
        $record->run()->associate($run);

        try {
            $brick = $this->bricks->resolve($step['brick']);
            $settings = $this->expression->resolve($step['settings'] ?? [], $context);
            $record->input = $settings;

            $validator = Validator::make($settings, $brick->rules());
            if ($validator->fails()) {
                return $this->finish($record, $started, RunStatus::Failed, error: implode(' ', $validator->errors()->all()));
            }

            if ($brick instanceof Branch) {
                $chosen = $brick->decide($context, $settings);
                $this->finish($record, $started, RunStatus::Completed, ['branch' => $chosen]);
                $context->recordOutput($step['id'], ['branch' => $chosen]);

                return $this->walk($step['branches'][$chosen] ?? [], $context, $run);
            }

            if ($brick instanceof Condition) {
                $passed = $brick->check($context, $settings);
                $context->recordOutput($step['id'], ['passed' => $passed]);

                return $this->finish($record, $started, RunStatus::Completed, ['passed' => $passed]);
            }

            if (! $brick instanceof Action) {
                throw new LogicException("Brick [{$step['brick']}] can't be used as a step.");
            }

            $result = $context->dryRun && ! $brick::$retrySafe
                ? $brick->dryRun($context, $settings)
                : $brick->handle($context, $settings);

            $context->recordOutput($step['id'], $result->output);

            return $this->finish($record, $started, $result->status, $result->output, $result->error);
        } catch (Throwable $e) {
            report($e);

            return $this->finish($record, $started, RunStatus::Failed, error: $e->getMessage());
        }
    }

    private function finish(FlowRunStep $record, int $started, RunStatus $status, array $output = [], ?string $error = null): RunStatus
    {
        $record->fill([
            'status' => $status,
            'output' => $output,
            'error' => $error,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ])->save();

        return $status;
    }
}
