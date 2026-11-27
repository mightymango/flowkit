<?php

namespace MightyMango\Flowkit\Facades;

use Illuminate\Support\Facades\Facade;
use MightyMango\Flowkit\Testing\FlowkitFake;
use MightyMango\Flowkit\TriggerDispatcher;

/**
 * @method static \MightyMango\Flowkit\Bricks\BrickRegistry bricks()
 * @method static \MightyMango\Flowkit\Flowkit register(string|array $bricks)
 * @method static \MightyMango\Flowkit\Engine\FlowBuilder define(string $key)
 * @method static \MightyMango\Flowkit\Models\FlowRun start(\MightyMango\Flowkit\Models\Flow|string $flow, array $payload = [], bool $dryRun = false)
 * @method static \MightyMango\Flowkit\Models\FlowRun run(\MightyMango\Flowkit\Models\Flow|string $flow, array $payload = [], bool $dryRun = false)
 * @method static \MightyMango\Flowkit\Models\FlowRun test(\MightyMango\Flowkit\Models\Flow|string $flow, array $payload = [])
 * @method static \MightyMango\Flowkit\Models\FlowRun retry(\MightyMango\Flowkit\Models\FlowRun $run)
 * @method static void assertStarted(\MightyMango\Flowkit\Models\Flow|string $flow, ?callable $callback = null)
 * @method static void assertStartedTimes(\MightyMango\Flowkit\Models\Flow|string $flow, int $times)
 * @method static void assertNotStarted(\MightyMango\Flowkit\Models\Flow|string $flow, ?callable $callback = null)
 * @method static void assertNothingStarted()
 * @method static void assertActionRan(string $brick, ?callable $callback = null)
 * @method static void assertActionRanTimes(string $brick, int $times)
 * @method static void assertActionNotRan(string $brick, ?callable $callback = null)
 * @method static \Illuminate\Support\Collection started(\MightyMango\Flowkit\Models\Flow|string|null $flow = null)
 * @method static \Illuminate\Support\Collection actions(?string $brick = null)
 * @method static void assertRetried(\MightyMango\Flowkit\Models\FlowRun $run)
 * @method static void assertNotRetried(\MightyMango\Flowkit\Models\FlowRun $run)
 * @method static void assertNothingRetried()
 * @method static \Illuminate\Support\Collection retried()
 *
 * @see \MightyMango\Flowkit\Flowkit
 * @see FlowkitFake
 */
class Flowkit extends Facade
{
    /**
     * Record the flows started instead of queueing them, like Queue::fake().
     * Runs started with Flowkit::run() or runFlow() still execute, and the
     * actions they call are recorded.
     */
    public static function fake(): FlowkitFake
    {
        $root = static::isFake() ? static::getFacadeRoot()->real() : static::getFacadeRoot();

        $fake = new FlowkitFake($root);

        static::swap($fake);

        // The dispatcher holds the instance it was built with.
        static::$app->forgetInstance(TriggerDispatcher::class);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \MightyMango\Flowkit\Flowkit::class;
    }
}
