<?php

namespace Act\Flowkit\Tests\Fixtures\Bricks;

use Act\Flowkit\Bricks\Action;
use Act\Flowkit\Engine\Context;
use Act\Flowkit\Engine\Result;
use Act\Flowkit\Fields\Text;
use Act\Flowkit\Fields\Textarea;

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
