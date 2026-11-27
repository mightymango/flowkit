<?php

namespace MightyMango\Flowkit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;
use MightyMango\Flowkit\Engine\Definition;

/**
 * An immutable snapshot of a flow definition once published.
 *
 * @property int $id
 * @property int $flow_id
 * @property int $version
 * @property array<string, mixed>|null $definition
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property-read Flow $flow
 */
class FlowVersion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'definition' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (FlowVersion $version) {
            if ($version->getOriginal('published_at') !== null && $version->isDirty('definition')) {
                throw new LogicException('Published flow versions are immutable. Edit a new draft instead.');
            }
        });
    }

    /** @return BelongsTo<Flow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /** @return HasMany<FlowRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(FlowRun::class);
    }

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function approver(): MorphTo
    {
        return $this->morphTo();
    }

    public function toDefinition(): Definition
    {
        return Definition::fromArray($this->definition ?? []);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
