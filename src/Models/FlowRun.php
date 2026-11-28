<?php

namespace MightyMango\Flowkit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use MightyMango\Flowkit\Enums\RunStatus;

/**
 * A run always points at a version, never at the flow, so editing a flow
 * never changes a run that is already under way.
 *
 * @property int $id
 * @property int $flow_version_id
 * @property string $correlation_id
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $context
 * @property RunStatus $status
 * @property bool $dry_run
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read FlowVersion $version
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

    /** @return BelongsTo<FlowVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class, 'flow_version_id');
    }

    protected static function booted(): void
    {
        // Claims aren't tied by a foreign key, so remove them here, including
        // when old runs are pruned.
        static::deleting(fn (FlowRun $run) => $run->claims()->delete());
    }

    /** @return HasMany<FlowClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(FlowClaim::class);
    }

    /** @return HasMany<FlowRunStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(FlowRunStep::class)->orderBy('id');
    }

    /**
     * The latest attempt at a step.
     */
    public function step(string $stepId): ?FlowRunStep
    {
        return $this->steps()->reorder()->where('step_id', $stepId)->latest('id')->first();
    }

    /**
     * Run history can contain personal data, so it is pruned after the
     * configured retention period by `php artisan model:prune`.
     */
    /**
     * @return Builder<FlowRun>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(config('flowkit.retention_days', 90)));
    }
}
