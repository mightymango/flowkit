<?php

namespace Act\Flowkit\Bricks;

use Act\Flowkit\Engine\Context;
use Act\Flowkit\Engine\Result;

/**
 * Does the work and returns outputs that later steps can use.
 */
abstract class Action extends Brick
{
    /**
     * Whether the action can be run twice without harm. Unsafe actions
     * (sending a message, calling an external API) should use
     * $context->idempotencyKey($stepId) and are not run in dry-run mode.
     */
    public static bool $retrySafe = true;

    abstract public function handle(Context $context, array $settings): Result;

    /**
     * What a dry run records instead of calling handle() on an unsafe action.
     */
    public function dryRun(Context $context, array $settings): Result
    {
        return Result::ok(['dry_run' => true, 'would_run_with' => $settings]);
    }

    public static function type(): string
    {
        return 'action';
    }
}
