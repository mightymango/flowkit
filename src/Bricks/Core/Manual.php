<?php

namespace Act\Flowkit\Bricks\Core;

use Act\Flowkit\Bricks\Trigger;

/**
 * Started from code or `php artisan flowkit:run`, with whatever payload is
 * passed in.
 */
class Manual extends Trigger
{
    public static string $key = 'core.manual';

    public static string $label = 'Run manually';

    public static string $group = 'Core';
}
