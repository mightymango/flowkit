<?php

namespace MightyMango\Flowkit\Testing;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Flowkit;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\Models\FlowRunStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Testing\Fakes\Fake;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Swapped in by Flowkit::fake(). Like Queue::fake(), runs started with
 * start() or test() are recorded as pending and never executed. Runs started
 * with run() (and so runFlow()) still execute in this process, and every
 * action they reach is recorded for assertActionRan().
 */
class FlowkitFake extends Flowkit implements Fake
{
    /** @var list<array{flow: string, payload: array, run: FlowRun}> */
    protected array $started = [];

    /** Step records at or below this id belong to runs from before the fake. */
    protected int $stepBaseline;

    public function __construct(protected Flowkit $real)
    {
        parent::__construct($real->bricks());

        $this->stepBaseline = (int) FlowRunStep::max('id');
    }

    public function real(): Flowkit
    {
        return $this->real;
    }

    public function start(Flow|string $flow, array $payload = [], bool $dryRun = false): FlowRun
    {
        return $this->record($this->createRun($flow, $payload, $dryRun));
    }

    public function run(Flow|string $flow, array $payload = [], bool $dryRun = false): FlowRun
    {
        return $this->execute($this->record($this->createRun($flow, $payload, $dryRun)));
    }

    /**
     * @param  callable(array $payload, FlowRun $run): bool|null  $callback
     */
    public function assertStarted(Flow|string $flow, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->started($flow, $callback)->isNotEmpty(),
            "The expected flow [{$this->key($flow)}] was not started."
        );
    }

    public function assertStartedTimes(Flow|string $flow, int $times): void
    {
        $count = $this->started($flow)->count();

        PHPUnit::assertSame(
            $times, $count,
            "The flow [{$this->key($flow)}] was started {$count} times instead of {$times} times."
        );
    }

    /**
     * @param  callable(array $payload, FlowRun $run): bool|null  $callback
     */
    public function assertNotStarted(Flow|string $flow, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->started($flow, $callback)->isEmpty(),
            "The unexpected flow [{$this->key($flow)}] was started."
        );
    }

    public function assertNothingStarted(): void
    {
        $keys = collect($this->started)->pluck('flow')->unique()->implode(', ');

        PHPUnit::assertEmpty($this->started, "Flows were started unexpectedly: {$keys}.");
    }

    /**
     * @param  callable(array $input, array $output, FlowRunStep $step): bool|null  $callback
     */
    public function assertActionRan(string $brick, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->actions($brick, $callback)->isNotEmpty(),
            "The expected action [{$brick}] did not run."
        );
    }

    public function assertActionRanTimes(string $brick, int $times): void
    {
        $count = $this->actions($brick)->count();

        PHPUnit::assertSame(
            $times, $count,
            "The action [{$brick}] ran {$count} times instead of {$times} times."
        );
    }

    /**
     * @param  callable(array $input, array $output, FlowRunStep $step): bool|null  $callback
     */
    public function assertActionNotRan(string $brick, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->actions($brick, $callback)->isEmpty(),
            "The unexpected action [{$brick}] ran."
        );
    }

    /**
     * Runs started since the fake was swapped in, optionally for one flow.
     *
     * @param  callable(array $payload, FlowRun $run): bool|null  $callback
     * @return Collection<int, FlowRun>
     */
    public function started(Flow|string|null $flow = null, ?callable $callback = null): Collection
    {
        return collect($this->started)
            ->when($flow !== null, fn (Collection $c) => $c->where('flow', $this->key($flow)))
            ->filter(fn (array $start) => ! $callback || $callback($start['payload'], $start['run']))
            ->pluck('run')
            ->values();
    }

    /**
     * Action steps the runner has reached since the fake was swapped in,
     * from the steps it records in flow_run_steps. Unsafe actions in a dry
     * run don't count: they never ran for real.
     *
     * @param  callable(array $input, array $output, FlowRunStep $step): bool|null  $callback
     * @return Collection<int, FlowRunStep>
     */
    public function actions(?string $brick = null, ?callable $callback = null): Collection
    {
        $actions = $this->bricks->all(Action::type());

        return FlowRunStep::query()
            ->with('run')
            ->where('id', '>', $this->stepBaseline)
            ->whereIn('brick', $brick === null ? array_keys($actions) : [$brick])
            ->orderBy('id')
            ->get()
            ->filter(fn (FlowRunStep $step) => isset($actions[$step->brick]))
            ->reject(fn (FlowRunStep $step) => $step->run->dry_run && ! $actions[$step->brick]::$retrySafe)
            ->filter(fn (FlowRunStep $step) => ! $callback || $callback($step->input ?? [], $step->output ?? [], $step))
            ->values();
    }

    protected function record(FlowRun $run): FlowRun
    {
        $this->started[] = [
            'flow' => $run->version->flow->key,
            'payload' => $run->payload ?? [],
            'run' => $run,
        ];

        return $run;
    }

    protected function key(Flow|string $flow): string
    {
        return $flow instanceof Flow ? $flow->key : $flow;
    }
}
