<?php

namespace MightyMango\Flowkit\Jobs;

use MightyMango\Flowkit\Engine\Runner;
use MightyMango\Flowkit\Models\FlowRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunFlow implements ShouldQueue
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
