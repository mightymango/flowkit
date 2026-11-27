<?php

namespace MightyMango\Flowkit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MightyMango\Flowkit\Enums\RunStatus;

/**
 * One attempt at one step of a run.
 *
 * @property int $id
 * @property int $flow_run_id
 * @property string $step_id
 * @property string $brick
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property RunStatus $status
 * @property string|null $error
 * @property int|null $duration_ms
 * @property int $attempt
 * @property-read FlowRun $run
 */
class FlowRunStep extends Model
{
    protected $guarded = [];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'status' => RunStatus::class,
    ];

    /** @return BelongsTo<FlowRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(FlowRun::class, 'flow_run_id');
    }
}
