<?php

namespace MightyMango\Flowkit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use MightyMango\Flowkit\Enums\RunStatus;

/**
 * A run has finished: completed or failed. Not fired when a run stops to
 * wait or is queued for a retry; it fires once the run really ends.
 *
 * Carries IDs and status only, never the payload or context.
 */
final class RunFinished
{
    use Dispatchable;

    public function __construct(
        public readonly int $runId,
        public readonly int $flowVersionId,
        public readonly string $correlationId,
        public readonly RunStatus $status,
        public readonly bool $dryRun,
    ) {}
}
