<?php

namespace MightyMango\Flowkit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use MightyMango\Flowkit\TriggerDispatcher;

/**
 * Schedule once in the host app, every minute:
 *
 *   Schedule::command('flowkit:tick')->everyMinute();
 *
 * Safe to overlap or to run on several servers: each flow is claimed once
 * per scheduled minute.
 */
class TickCommand extends Command
{
    protected $signature = 'flowkit:tick';

    protected $description = 'Start scheduled flows that are due this minute';

    public function handle(TriggerDispatcher $dispatcher): int
    {
        $started = $dispatcher->schedule();

        $this->components->info("Started {$started} scheduled ".Str::plural('flow', $started).'.');

        return self::SUCCESS;
    }
}
