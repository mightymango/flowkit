<?php

namespace MightyMango\Flowkit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \MightyMango\Flowkit\Bricks\BrickRegistry bricks()
 * @method static \MightyMango\Flowkit\Flowkit register(string|array $bricks)
 * @method static \MightyMango\Flowkit\Engine\FlowBuilder define(string $key)
 * @method static \MightyMango\Flowkit\Models\FlowRun start(\MightyMango\Flowkit\Models\Flow|string $flow, array $payload = [], bool $dryRun = false)
 * @method static \MightyMango\Flowkit\Models\FlowRun test(\MightyMango\Flowkit\Models\Flow|string $flow, array $payload = [])
 *
 * @see \MightyMango\Flowkit\Flowkit
 */
class Flowkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \MightyMango\Flowkit\Flowkit::class;
    }
}
