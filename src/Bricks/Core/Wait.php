<?php

namespace MightyMango\Flowkit\Bricks\Core;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Result;
use MightyMango\Flowkit\Fields\Duration;

/**
 * Pause the run. The runner marks the run as waiting; resuming it is the job
 * of the durable-workflow layer, which lands in phase 3.
 */
class Wait extends Action
{
    public static string $key = 'core.wait';

    public static string $label = 'Wait';

    public static string $group = 'Logic';

    public function fields(): array
    {
        return [
            Duration::make('duration')->label('Wait for')->required(),
        ];
    }

    public function handle(Context $context, array $settings): Result
    {
        return Result::wait($settings['duration']);
    }
}
