<?php

namespace MightyMango\Flowkit\Tests\Fixtures\Bricks;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Result;
use RuntimeException;

/**
 * Throws until it has been called `$failures` times, then succeeds.
 */
class Flaky extends Action
{
    public static string $key = 'test.flaky';

    public static string $label = 'Flaky call';

    public static int $failures = 0;

    public static int $calls = 0;

    public function handle(Context $context, array $settings): Result
    {
        static::$calls++;

        if (static::$calls <= static::$failures) {
            throw new RuntimeException('Service unavailable');
        }

        return Result::ok(['calls' => static::$calls]);
    }
}
