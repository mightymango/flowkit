<?php

namespace MightyMango\Flowkit\Models;

use MightyMango\Flowkit\Engine\Definition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * An immutable snapshot of a flow definition once published.
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

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(FlowRun::class);
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

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
