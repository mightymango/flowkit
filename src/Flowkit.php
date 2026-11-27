<?php

namespace MightyMango\Flowkit;

use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Engine\FlowBuilder;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Jobs\RunFlow;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Models\FlowRun;
use Illuminate\Support\Str;
use RuntimeException;

class Flowkit
{
    public function __construct(private BrickRegistry $bricks) {}

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
        $flow = $flow instanceof Flow ? $flow : Flow::where('key', $flow)->firstOrFail();

        if (! $flow->isRunnable() && ! $dryRun) {
            throw new RuntimeException("Flow [{$flow->key}] is not live, or is paused.");
        }

        $version = $flow->liveVersion ?? $flow->latestVersion;

        $run = $version->runs()->create([
            'correlation_id' => (string) Str::uuid(),
            'payload' => $payload,
            'status' => RunStatus::Pending,
            'dry_run' => $dryRun,
        ]);

        RunFlow::dispatch($run);

        return $run;
    }

    /**
     * Resume a failed run from the step that failed. Steps that completed
     * are not run again. This retries any action, including one that isn't
     * safe to repeat, so check what the failed step did first.
     */
    public function retry(FlowRun $run): FlowRun
    {
        if ($run->status !== RunStatus::Failed) {
            throw new RuntimeException("Run #{$run->id} hasn't failed, so it can't be retried.");
        }

        $run->forceFill(['status' => RunStatus::Pending, 'finished_at' => null])->save();

        RunFlow::dispatch($run);

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
}
