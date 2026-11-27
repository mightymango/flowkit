<?php

namespace MightyMango\Flowkit;

use MightyMango\Flowkit\Bricks\Core\ModelEvent;
use MightyMango\Flowkit\Enums\FlowStatus;
use MightyMango\Flowkit\Models\Flow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Finds live flows whose trigger matches an event and starts a run of each.
 */
class TriggerDispatcher
{
    public function __construct(private Flowkit $flowkit) {}

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

        $this->fire(ModelEvent::$key, $payload, function (array $settings) use ($model, $event, $changes, $rules) {
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
     * @param  callable(array $settings): bool|null  $matches
     */
    public function fire(string $triggerKey, array $payload, ?callable $matches = null): int
    {
        if (! config('flowkit.enabled', true)) {
            return 0;
        }

        $started = 0;

        Flow::query()
            ->where('status', FlowStatus::Live)
            ->where('paused', false)
            ->with('liveVersion')
            ->each(function (Flow $flow) use ($triggerKey, $payload, $matches, &$started) {
                $trigger = $flow->liveVersion?->definition['trigger'] ?? null;

                if (($trigger['brick'] ?? null) !== $triggerKey) {
                    return;
                }

                if ($matches && ! $matches($trigger['settings'] ?? [])) {
                    return;
                }

                $this->flowkit->start($flow, $payload);
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
