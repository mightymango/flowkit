<?php

namespace Act\Flowkit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Act\Flowkit\Bricks\BrickRegistry bricks()
 * @method static \Act\Flowkit\Flowkit register(string|array $bricks)
 * @method static \Act\Flowkit\Engine\FlowBuilder define(string $key)
 * @method static \Act\Flowkit\Models\FlowRun start(\Act\Flowkit\Models\Flow|string $flow, array $payload = [], bool $dryRun = false)
 * @method static \Act\Flowkit\Models\FlowRun test(\Act\Flowkit\Models\Flow|string $flow, array $payload = [])
 *
 * @see \Act\Flowkit\Flowkit
 */
class Flowkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Act\Flowkit\Flowkit::class;
    }
}
