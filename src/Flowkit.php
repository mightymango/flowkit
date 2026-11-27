<?php

namespace MightyMango\Flowkit;

use Illuminate\Support\Str;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Engine\FlowBuilder;
use MightyMango\Flowkit\Engine\Runner;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Jobs\RunFlow;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Models\FlowRun;
use RuntimeException;

class Flowkit
{
    public function __construct(protected BrickRegistry $bricks) {}

    public function bricks(): BrickRegistry
    {
        return $this->bricks;
    }

    /**
     * @param  class-string<Bricks\Brick>|list<class-string<Bricks\Brick>>  $bricks
     */
    public function register(string|array $bricks): self
    {
        $this->bricks->register($bricks);

        return $this;
    }

    public function define(string $key): FlowBuilder
    {
        return new FlowBuilder($key);
    }

    /**
     * Start a run of a flow's live version on the queue.
     */
    public function start(Flow|string $flow, array $payload = [], bool $dryRun = false): FlowRun
    {
        $run = $this->createRun($flow, $payload, $dryRun);

        RunFlow::dispatch($run);

        return $run;
    }

    /**
     * Run a flow's live version now, in this process, instead of on the
     * queue. Returns the run once it has finished or is waiting.
     */
    public function run(Flow|string $flow, array $payload = [], bool $dryRun = false): FlowRun
    {
        return $this->execute($this->createRun($flow, $payload, $dryRun));
    }

    /**
     * Resume a failed run from the step that failed. Steps that completed
     * are not run again. This retries any action, including one that isn't
     * safe to repeat, so check what the failed step did first.
     */
    public function retry(FlowRun $run): FlowRun
    {
        RunFlow::dispatch($this->prepareRetry($run));

        return $run;
    }

    /**
     * Check a run can be retried and put it back to pending.
     */
    protected function prepareRetry(FlowRun $run): FlowRun
    {
        if ($run->status !== RunStatus::Failed) {
            throw new RuntimeException("Run #{$run->id} hasn't failed, so it can't be retried.");
        }

        $run->forceFill(['status' => RunStatus::Pending, 'finished_at' => null])->save();

        return $run;
    }

    /**
     * Run against the flow with unsafe actions in dry-run mode, logging what
     * each would have done.
     */
    public function test(Flow|string $flow, array $payload = []): FlowRun
    {
        return $this->start($flow, $payload, dryRun: true);
    }

    /**
     * Record a pending run of the flow's live version without starting it.
     */
    protected function createRun(Flow|string $flow, array $payload, bool $dryRun): FlowRun
    {
        $flow = $flow instanceof Flow ? $flow : Flow::where('key', $flow)->firstOrFail();

        if (! $flow->isRunnable() && ! $dryRun) {
            throw new RuntimeException("Flow [{$flow->key}] is not live, or is paused.");
        }

        $version = $flow->liveVersion ?? $flow->latestVersion;

        return $version->runs()->create([
            'correlation_id' => (string) Str::uuid(),
            'payload' => $payload,
            'status' => RunStatus::Pending,
            'dry_run' => $dryRun,
        ]);
    }

    protected function execute(FlowRun $run): FlowRun
    {
        return app(Runner::class)->execute($run)->refresh();
    }
}
