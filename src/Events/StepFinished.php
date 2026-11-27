<?php

namespace MightyMango\Flowkit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use MightyMango\Flowkit\Enums\RunStatus;

/**
 * The runner has recorded an attempt at a step, in flow_run_steps row
 * $stepRecordId. Not fired for steps a resumed run skips because they
 * already completed.
 *
 * $handled is true when the brick's own logic ran for real: an action's
 * handle(), a condition's check() or a branch's decision. It's false when
 * the settings failed validation, the brick couldn't be resolved, or an
 * unsafe action was replaced by its dry run.
 *
 * Carries IDs and status only, never input or output: run data can be
 * personal. Load the FlowRunStep if you need more, and take care with it.
 */
final class StepFinished
{
    use Dispatchable;

    public function __construct(
        public readonly int $runId,
        public readonly int $stepRecordId,
        public readonly string $stepId,
        public readonly string $brick,
        public readonly RunStatus $status,
        public readonly int $attempt,
        public readonly bool $handled,
    ) {}
}
