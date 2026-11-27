<?php

namespace MightyMango\Flowkit;

use DateTimeInterface;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Core\ModelEvent;
use MightyMango\Flowkit\Bricks\Core\Schedule;
use MightyMango\Flowkit\Enums\FlowStatus;
use MightyMango\Flowkit\Models\Flow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Finds live flows whose trigger matches an event and starts a run of each.
 */
class TriggerDispatcher
{
    public function __construct(private Flowkit $flowkit, private BrickRegistry $bricks) {}

    public function modelEvent(Model $model, string $event): void
    {
        $alias = Str::snake(class_basename($model));
        $payload = [
            $alias => method_exists($model, 'toFlowPayload') ? $model->toFlowPayload() : $model->attributesToArray(),
            'event' => $event,
        ];

        $this->fire(ModelEvent::$key, $payload, function (array $settings) use ($model, $event) {
            return ltrim($settings['model'] ?? '', '\\') === $model::class
                && ($settings['event'] ?? null) === $event;
        });
    }

    /**
     * Start every live flow with a `core.schedule` trigger that is due in the
     * current minute. Called by `flowkit:tick` every minute.
     *
     * Each flow is claimed for the minute with one conditional UPDATE on
     * `flows.last_scheduled_at` before it starts. The database applies it
     * atomically, so if ticks overlap or two servers tick at once only one
     * of them changes the row and starts a run; the others see 0 rows
     * affected and skip it. This needs no shared cache or lock store.
     */
    public function schedule(?DateTimeInterface $now = null): int
    {
        $minute = Carbon::instance($now ?? now())->utc()->startOfMinute();
        $brick = $this->bricks->resolve(Schedule::$key);

        return $this->fire(Schedule::$key, [], function (array $settings, Flow $flow) use ($brick, $minute) {
            // Never trust stored settings: an invalid cron is skipped, not run.
            if (Validator::make($settings, $brick->rules())->fails()) {
                return false;
            }

            if (! $brick->isDue($settings, $minute)) {
                return false;
            }

            return $this->claim($flow, $minute);
        }, fn (array $settings) => ['schedule' => [
            'scheduled_at' => $minute->copy()->setTimezone($brick->timezone($settings))->toIso8601String(),
            'timezone' => $brick->timezone($settings),
        ]]);
    }

    /**
     * Atomically mark the flow as scheduled for this minute. True only for
     * the one caller whose update changed the row.
     */
    private function claim(Flow $flow, Carbon $minute): bool
    {
        return Flow::query()
            ->whereKey($flow->getKey())
            ->where(fn ($query) => $query
                ->whereNull('last_scheduled_at')
                ->orWhere('last_scheduled_at', '<', $minute))
            ->toBase()
            ->update(['last_scheduled_at' => $minute]) === 1;
    }

    /**
     * @param  callable(array $settings, Flow $flow): bool|null  $matches
     * @param  callable(array $settings): array|null  $payloadFor  extra payload per flow, merged over $payload
     */
    public function fire(string $triggerKey, array $payload, ?callable $matches = null, ?callable $payloadFor = null): int
    {
        if (! config('flowkit.enabled', true)) {
            return 0;
        }

        $started = 0;

        Flow::query()
            ->where('status', FlowStatus::Live)
            ->where('paused', false)
            ->with('liveVersion')
            ->each(function (Flow $flow) use ($triggerKey, $payload, $matches, $payloadFor, &$started) {
                $trigger = $flow->liveVersion?->definition['trigger'] ?? null;

                if (($trigger['brick'] ?? null) !== $triggerKey) {
                    return;
                }

                $settings = $trigger['settings'] ?? [];

                if ($matches && ! $matches($settings, $flow)) {
                    return;
                }

                $this->flowkit->start($flow, $payloadFor ? array_merge($payload, $payloadFor($settings)) : $payload);
                $started++;
            });

        return $started;
    }
}
