<?php

namespace MightyMango\Flowkit\Testing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Testing\Fakes\Fake;
use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Events\StepFinished;
use MightyMango\Flowkit\Flowkit;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\Models\FlowRunStep;
use MightyMango\Flowkit\Models\FlowVersion;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Swapped in by Flowkit::fake(). Like Queue::fake(), runs started with
 * start() or test(), and runs resumed with retry(), are recorded as pending
 * and never executed. Runs started
 * with run() (and so runFlow()) still execute in this process, and every
 * action whose handle() runs is recorded for assertActionRan(), from the
 * runner's StepFinished events. (Event::fake() stops those events, so don't
 * combine the two if you assert on actions.)
 */
class FlowkitFake extends Flowkit implements Fake
{
    /** @var list<array{flow: string, payload: array, run: FlowRun}> */
    protected array $started = [];

    /** @var list<FlowRun> */
    protected array $retried = [];

    /** @var list<StepFinished> */
    protected array $steps = [];

    public function __construct(protected Flowkit $real)
    {
        parent::__construct($real->bricks());

        Event::listen(StepFinished::class, function (StepFinished $event) {
            $this->steps[] = $event;
        });
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

    public function testVersion(FlowVersion $version, array $payload = []): FlowRun
    {
        return $this->execute($this->record($this->createRunOf($version, $payload, dryRun: true)));
    }

    /**
     * Records the retry without running it. Like a real retry, it refuses a
     * run that hasn't failed and puts the run back to pending.
     */
    public function retry(FlowRun $run): FlowRun
    {
        $this->retried[] = $this->prepareRetry($run);

        return $run;
    }

    public function assertRetried(FlowRun $run): void
    {
        PHPUnit::assertTrue(
            $this->retried()->contains(fn (FlowRun $retried) => $retried->is($run)),
            "Run #{$run->id} was not retried."
        );
    }

    public function assertNotRetried(FlowRun $run): void
    {
        PHPUnit::assertFalse(
            $this->retried()->contains(fn (FlowRun $retried) => $retried->is($run)),
            "Run #{$run->id} was retried unexpectedly."
        );
    }

    public function assertNothingRetried(): void
    {
        $ids = $this->retried()->map(fn (FlowRun $run) => "#{$run->id}")->implode(', ');

        PHPUnit::assertEmpty($this->retried, "Runs were retried unexpectedly: {$ids}.");
    }

    /**
     * Runs retried since the fake was swapped in.
     *
     * @return Collection<int, FlowRun>
     */
    public function retried(): Collection
    {
        return collect($this->retried);
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
     * Attempts at action steps whose handle() really ran since the fake was
     * swapped in, from the runner's StepFinished events. A step that failed
     * validation, or an unsafe action replaced by its dry run, doesn't count.
     *
     * @param  callable(array $input, array $output, FlowRunStep $step): bool|null  $callback
     * @return Collection<int, FlowRunStep>
     */
    public function actions(?string $brick = null, ?callable $callback = null): Collection
    {
        $actions = $this->bricks->all(Action::type());

        $ids = collect($this->steps)
            ->filter(fn (StepFinished $event) => $event->handled && isset($actions[$event->brick]))
            ->when($brick !== null, fn (Collection $c) => $c->where('brick', $brick))
            ->map(fn (StepFinished $event) => $event->stepRecordId)
            ->all();

        return FlowRunStep::query()
            ->whereKey($ids)
            ->orderBy('id')
            ->get()
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
