<?php

namespace MightyMango\Flowkit\Bricks;

use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Result;

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

    /**
     * Set on an unsafe action whose handle() checks
     * $context->idempotencyKey($stepId), so that a retry can't do its work
     * twice. Such an action is retried like a safe one.
     */
    public static bool $usesIdempotencyKey = false;

    /**
     * How many attempts to make when handle() throws, including the first.
     * Null uses `flowkit.retries.tries`. Only applies to actions that are
     * safe to retry; others get one automatic attempt.
     */
    public static ?int $tries = null;

    abstract public function handle(Context $context, array $settings): Result;

    /**
     * What a dry run records instead of calling handle() on an unsafe action.
     */
    public function dryRun(Context $context, array $settings): Result
    {
        return Result::ok(['dry_run' => true, 'would_run_with' => $settings]);
    }

    public function tries(): int
    {
        if (! static::$retrySafe && ! static::$usesIdempotencyKey) {
            return 1;
        }

        return max(1, static::$tries ?? (int) config('flowkit.retries.tries', 3));
    }

    /**
     * Seconds to wait before the attempt after $attempt.
     */
    public function backoff(int $attempt): int
    {
        $delays = array_values((array) config('flowkit.retries.backoff', [10, 60, 300]));

        return $delays ? (int) $delays[min($attempt, count($delays)) - 1] : 0;
    }

    public static function type(): string
    {
        return 'action';
    }
}
