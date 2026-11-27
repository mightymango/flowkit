<?php

namespace MightyMango\Flowkit\Models;

use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Enums\FlowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Flow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => FlowStatus::class,
        'paused' => 'boolean',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FlowVersion::class)->orderBy('version');
    }

    public function liveVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class, 'live_version_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(FlowVersion::class)->latestOfMany('version');
    }

    /**
     * The newest unpublished version, creating one from the latest (or an
     * empty definition) if every existing version has been published.
     * Runs in progress keep using the version they started on.
     */
    public function draft(): FlowVersion
    {
        $latest = $this->latestVersion()->first();

        if ($latest && $latest->published_at === null) {
            return $latest;
        }

        return $this->versions()->create([
            'version' => ($latest?->version ?? 0) + 1,
            'definition' => $latest?->definition ?? Definition::empty()->toArray(),
        ]);
    }

    /**
     * Publish a version and make it the one new runs use.
     */
    public function publish(FlowVersion $version, $approver = null): FlowVersion
    {
        Definition::fromArray($version->definition)->validate();

        if ($approver) {
            $version->approver()->associate($approver);
        }

        $version->published_at = now();
        $version->save();

        $this->forceFill([
            'live_version_id' => $version->id,
            'status' => FlowStatus::Live,
        ])->save();

        return $version;
    }

    public function isRunnable(): bool
    {
        return $this->status === FlowStatus::Live
            && ! $this->paused
            && $this->live_version_id !== null
            && config('flowkit.enabled', true);
    }
}
