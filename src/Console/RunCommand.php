<?php

namespace Act\Flowkit\Console;

use Act\Flowkit\Flowkit;
use Illuminate\Console\Command;

class RunCommand extends Command
{
    protected $signature = 'flowkit:run
        {flow : The flow key}
        {--payload= : JSON payload for the trigger}
        {--dry-run : Run unsafe actions in dry-run mode}';

    protected $description = 'Start a run of a flow';

    public function handle(Flowkit $flowkit): int
    {
        $payload = $this->option('payload') ? json_decode($this->option('payload'), true) : [];

        if (! is_array($payload)) {
            $this->components->error('The payload must be a JSON object.');

            return self::FAILURE;
        }

        $run = $flowkit->start($this->argument('flow'), $payload, (bool) $this->option('dry-run'));

        $this->components->info("Run #{$run->id} queued ({$run->correlation_id}).");

        return self::SUCCESS;
    }
}
