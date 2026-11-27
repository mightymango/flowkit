<?php

namespace MightyMango\Flowkit\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The runner has started executing a run. Fired again when a run resumes,
 * after a retry or by hand, with $resumed set.
 *
 * Carries IDs only, never the payload or context: run data can be personal.
 */
final class RunStarted
{
    use Dispatchable;

    public function __construct(
        public readonly int $runId,
        public readonly int $flowVersionId,
        public readonly string $correlationId,
        public readonly bool $dryRun,
        public readonly bool $resumed,
    ) {}
}
