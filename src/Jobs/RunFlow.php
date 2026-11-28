<?php

namespace MightyMango\Flowkit\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MightyMango\Flowkit\Engine\Runner;
use MightyMango\Flowkit\Models\FlowRun;

/**
 * Queued only once any open database transaction commits. A trigger often
 * fires inside one (e.g. a model saved in a transaction), and the run's own
 * row is created there too, so a worker that picked the job up earlier
 * might not find it, or might run a flow for a change that's rolled back.
 */
class RunFlow implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Failures are recorded per step rather than retried at job level, so a
     * retry can never repeat an action that isn't safe to repeat.
     */
    public int $tries = 1;

    public function __construct(public FlowRun $run)
    {
        $this->onConnection(config('flowkit.queue.connection'));
        $this->onQueue(config('flowkit.queue.name'));
    }

    public function handle(Runner $runner): void
    {
        $runner->execute($this->run);
    }
}
