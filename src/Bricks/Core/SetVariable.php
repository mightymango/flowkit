<?php

namespace MightyMango\Flowkit\Bricks\Core;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Result;
use MightyMango\Flowkit\Fields\Text;

/**
 * Store a value for later steps, available as `steps.<id>.value`.
 */
class SetVariable extends Action
{
    public static string $key = 'core.set-variable';

    public static string $label = 'Set a variable';

    public static string $group = 'Logic';

    public function fields(): array
    {
        return [
            Text::make('value')->label('Value')->supportsVariables()->required(),
        ];
    }

    public function handle(Context $context, array $settings): Result
    {
        return Result::ok(['value' => $settings['value'] ?? null]);
    }
}
