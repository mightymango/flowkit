<?php

namespace Act\Flowkit\Bricks\Core;

use Act\Flowkit\Bricks\Action;
use Act\Flowkit\Engine\Context;
use Act\Flowkit\Engine\Result;
use Act\Flowkit\Fields\Text;

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
