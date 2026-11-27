<?php

namespace MightyMango\Flowkit\Tests\Fixtures\Bricks;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Result;
use MightyMango\Flowkit\Fields\Text;
use MightyMango\Flowkit\Fields\Textarea;

class SendMessage extends Action
{
    public static string $key = 'test.send-message';

    public static string $label = 'Send a message';

    public static bool $retrySafe = false;

    public static array $sent = [];

    public function fields(): array
    {
        return [
            Text::make('to')->supportsVariables()->required(),
            Textarea::make('message')->supportsVariables()->required(),
        ];
    }

    public function handle(Context $context, array $settings): Result
    {
        static::$sent[] = $settings + ['key' => $context->idempotencyKey('x')];

        return Result::ok(['message_id' => count(static::$sent)]);
    }
}
