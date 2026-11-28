<?php

namespace MightyMango\Flowkit;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LogicException;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Core\ModelEvent;
use MightyMango\Flowkit\Bricks\Core\Schedule;
use MightyMango\Flowkit\Engine\TriggerKey;
use MightyMango\Flowkit\Enums\FlowStatus;
use MightyMango\Flowkit\Models\Flow;

/**
 * Finds live flows whose trigger matches an event and starts a run of each.
 */
class TriggerDispatcher
{
    public function __construct(private Flowkit $flowkit, private BrickRegistry $bricks) {}

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes  for `changed`: attribute => old and new values
     */
    public function modelEvent(Model $model, string $event, array $changes = []): void
    {
        $alias = Str::snake(class_basename($model));
        $payload = [
            $alias => method_exists($model, 'toFlowPayload') ? $model->toFlowPayload() : $model->attributesToArray(),
            'event' => $event,
        ];

        if ($event === 'changed') {
            $payload['changes'] = $changes;
        }

        $rules = app(ModelEvent::class)->rules();
        $key = TriggerKey::modelEvent($model::class, $event);

        // Only load flows indexed for this model and event; the full settings
        // check below still decides whether each one matches.
        $this->dispatch(ModelEvent::$key, fn (Builder $query) => $query->where('trigger_key', $key), $payload, function (array $settings) use ($model, $event, $changes, $rules) {
            // Never trust a stored definition: a trigger with invalid settings matches nothing.
            if (Validator::make($settings, $rules)->fails()) {
                return false;
            }

            if (ltrim($settings['model'] ?? '', '\\') !== $model::class || ($settings['event'] ?? null) !== $event) {
                return false;
            }

            if ($event !== 'changed') {
                return true;
            }

            $change = $changes[$settings['attribute'] ?? ''] ?? null;

            return $change !== null
                && $this->valueMatches($settings['from'] ?? null, $change['from'])
                && $this->valueMatches($settings['to'] ?? null, $change['to']);
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

        if (! $brick instanceof Schedule) {
            throw new LogicException('The core.schedule brick has been replaced by one that is not a Schedule trigger.');
        }

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
     * Start every live flow whose trigger is `$triggerKey` and matches.
     *
     * @param  callable(array $settings, Flow $flow): bool|null  $matches
     * @param  callable(array $settings): array|null  $payloadFor  extra payload per flow, merged over $payload
     */
    public function fire(string $triggerKey, array $payload, ?callable $matches = null, ?callable $payloadFor = null): int
    {
        // A trigger keyed by more than its brick (see TriggerKey) is stored as
        // `<brick>:...`. The brick check in dispatch() makes the match exact.
        return $this->dispatch($triggerKey, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('trigger_key', $triggerKey)
            ->orWhere('trigger_key', 'like', $triggerKey.':%')), $payload, $matches, $payloadFor);
    }

    /**
     * @param  callable(Builder<Flow>): mixed  $narrow  limits the query by `trigger_key`
     * @param  callable(array $settings, Flow $flow): bool|null  $matches
     * @param  callable(array $settings): array|null  $payloadFor
     */
    private function dispatch(string $brick, callable $narrow, array $payload, ?callable $matches = null, ?callable $payloadFor = null): int
    {
        if (! config('flowkit.enabled', true)) {
            return 0;
        }

        $started = 0;

        Flow::query()
            ->where('status', FlowStatus::Live)
            ->where('paused', false)
            ->tap($narrow)
            ->with('liveVersion')
            ->each(function (Flow $flow) use ($brick, $payload, $matches, $payloadFor, &$started) {
                $trigger = $flow->liveVersion?->definition['trigger'] ?? null;

                if (($trigger['brick'] ?? null) !== $brick) {
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

    /** An empty filter matches any value; otherwise compare as strings. */
    private function valueMatches(?string $expected, mixed $actual): bool
    {
        if ($expected === null || $expected === '') {
            return true;
        }

        return is_scalar($actual) && (string) $actual === $expected;
    }
}
