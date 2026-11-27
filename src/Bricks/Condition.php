<?php

namespace Act\Flowkit\Bricks;

use Act\Flowkit\Engine\Context;

/**
 * Returns true or false. Used by branch steps.
 */
abstract class Condition extends Brick
{
    abstract public function check(Context $context, array $settings): bool;

    public static function type(): string
    {
        return 'condition';
    }
}
