<?php

namespace Act\Flowkit;

use Act\Flowkit\Bricks\Core\ModelEvent;
use Act\Flowkit\Enums\FlowStatus;
use Act\Flowkit\Models\Flow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Finds live flows whose trigger matches an event and starts a run of each.
 */
class TriggerDispatcher
{
    public function __construct(private Flowkit $flowkit) {}

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
}
