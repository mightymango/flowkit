<?php

use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Testing\TestRun;

if (! function_exists('runFlow')) {
    /**
     * Run a flow now, in this process, and return it with assertions.
     *
     *   runFlow('new-starter', $payload)->assertStepCompleted('s3');
     */
    function runFlow(Flow|string $flow, array $payload = [], bool $dryRun = false): TestRun
    {
        return new TestRun(Flowkit::run($flow, $payload, $dryRun));
    }
}
