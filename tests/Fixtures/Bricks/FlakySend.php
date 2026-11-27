<?php

namespace MightyMango\Flowkit\Tests\Fixtures\Bricks;

/**
 * An unsafe action that throws; with $usesIdempotencyKey it may be retried.
 */
class FlakySend extends Flaky
{
    public static string $key = 'test.flaky-send';

    public static string $label = 'Flaky send';

    public static bool $retrySafe = false;
}
