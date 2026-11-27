<?php

namespace Act\Flowkit\Models;

use Act\Flowkit\Enums\RunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A run always points at a version, never at the flow, so editing a flow
 * never changes a run that is already under way.
 */
class FlowRun extends Model
{
    use Prunable;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'context' => 'array',
        'status' => RunStatus::class,
        'dry_run' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class, 'flow_version_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FlowRunStep::class)->orderBy('id');
    }

    public function step(string $stepId): ?FlowRunStep
    {
        return $this->steps()->where('step_id', $stepId)->latest('id')->first();
    }

    /**
     * Run history can contain personal data, so it is pruned after the
     * configured retention period by `php artisan model:prune`.
     */
    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(config('flowkit.retention_days', 90)));
    }
}
