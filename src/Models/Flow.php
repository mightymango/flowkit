<?php

namespace MightyMango\Flowkit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Enums\FlowStatus;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property FlowStatus|null $status
 * @property int|null $live_version_id
 * @property bool $paused
 * @property string|null $last_scheduled_at
 * @property-read FlowVersion|null $liveVersion
 * @property-read FlowVersion|null $latestVersion
 */
class Flow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => FlowStatus::class,
        'paused' => 'boolean',
    ];

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<FlowVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FlowVersion::class)->orderBy('version');
    }

    /**
     * Runs of every version of this flow.
     *
     * @return HasManyThrough<FlowRun, FlowVersion, $this>
     */
    public function runs(): HasManyThrough
    {
        return $this->hasManyThrough(FlowRun::class, FlowVersion::class);
    }

    /** @return BelongsTo<FlowVersion, $this> */
    public function liveVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class, 'live_version_id');
    }

    /** @return HasOne<FlowVersion, $this> */
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
            'version' => $latest ? $latest->version + 1 : 1,
            'definition' => $latest ? $latest->definition : Definition::empty()->toArray(),
        ]);
    }

    /**
     * Publish a version and make it the one new runs use.
     */
    public function publish(FlowVersion $version, ?Model $approver = null): FlowVersion
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

    /** The owner's name (or email) for display, if the flow has an owner. */
    public function ownerName(): ?string
    {
        $owner = $this->owner;

        return $owner ? (data_get($owner, 'name') ?? data_get($owner, 'email')) : null;
    }

    public function isRunnable(): bool
    {
        return $this->status === FlowStatus::Live
            && ! $this->paused
            && $this->live_version_id !== null
            && config('flowkit.enabled', true);
    }
}
