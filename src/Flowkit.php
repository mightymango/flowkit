<?php

namespace Act\Flowkit;

use Act\Flowkit\Bricks\BrickRegistry;
use Act\Flowkit\Engine\FlowBuilder;
use Act\Flowkit\Enums\RunStatus;
use Act\Flowkit\Jobs\RunFlow;
use Act\Flowkit\Models\Flow;
use Act\Flowkit\Models\FlowRun;
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
     * Run against the flow with unsafe actions in dry-run mode, logging what
     * each would have done.
     */
    public function test(Flow|string $flow, array $payload = []): FlowRun
    {
        return $this->start($flow, $payload, dryRun: true);
    }
}
