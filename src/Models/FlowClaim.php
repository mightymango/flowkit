<?php

namespace MightyMango\Flowkit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record that a run's step has done something that must only happen once,
 * such as sending an email. Created by Context::claimOnce() and deleted
 * with its run.
 *
 * @property int $id
 * @property string $key
 * @property int|null $flow_run_id
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class FlowClaim extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * Claim a key. Returns false if it has already been claimed.
     */
    public static function claim(string $key, ?int $runId): bool
    {
        return static::query()->insertOrIgnore([
            'key' => $key,
            'flow_run_id' => $runId,
            'created_at' => now(),
        ]) === 1;
    }
}
