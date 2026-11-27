<?php

namespace MightyMango\Flowkit\Console;

use Illuminate\Console\Command;
use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Flowkit;
use MightyMango\Flowkit\Models\FlowRun;

class RetryCommand extends Command
{
    protected $signature = 'flowkit:retry
        {run : The ID of the failed run}
        {--force : Skip the confirmation for a step that isn\'t safe to repeat}';

    protected $description = 'Resume a failed run from the step that failed';

    public function handle(Flowkit $flowkit): int
    {
        $run = FlowRun::find($this->argument('run'));

        if (! $run) {
            $this->components->error("Run #{$this->argument('run')} doesn't exist.");

            return self::FAILURE;
        }

        if ($run->status !== RunStatus::Failed) {
            $this->components->error("Run #{$run->id} is {$run->status->value}, not failed, so it can't be retried.");

            return self::FAILURE;
        }

        $failed = $run->steps()->where('status', RunStatus::Failed)->latest('id')->first();
        $brick = $failed && $flowkit->bricks()->has($failed->brick) ? $flowkit->bricks()->all()[$failed->brick] : null;
        $unsafe = $brick && is_subclass_of($brick, Action::class)
            && ! $brick::$retrySafe && ! $brick::$usesIdempotencyKey;

        if ($unsafe && ! $this->option('force')
            && ! $this->confirm("Step [{$failed->step_id}] ({$brick::$label}) isn't safe to repeat and may already have done its work. Retry it anyway?")) {
            return self::FAILURE;
        }

        $flowkit->retry($run);

        $this->components->info("Run #{$run->id} queued to resume".($failed ? " from step [{$failed->step_id}]" : '').'.');

        return self::SUCCESS;
    }
}
