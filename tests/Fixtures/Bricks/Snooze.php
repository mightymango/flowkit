<?php

namespace MightyMango\Flowkit\Tests\Fixtures\Bricks;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Result;
use MightyMango\Flowkit\Fields\Number;

/** An action with Number fields, for testing the number field. */
class Snooze extends Action
{
    public static string $key = 'test.snooze';

    public static string $label = 'Snooze';

    public function fields(): array
    {
        return [
            Number::make('minutes')->integer()->min(1)->max(60)->supportsVariables()->required(),
            Number::make('ratio')->min(0)->max(1),
        ];
    }

    public function handle(Context $context, array $settings): Result
    {
        return Result::ok(['minutes' => $settings['minutes']]);
    }
}
