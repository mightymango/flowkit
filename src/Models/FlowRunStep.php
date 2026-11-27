<?php

namespace MightyMango\Flowkit\Models;

use MightyMango\Flowkit\Enums\RunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowRunStep extends Model
{
    protected $guarded = [];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'status' => RunStatus::class,
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(FlowRun::class, 'flow_run_id');
    }
}
