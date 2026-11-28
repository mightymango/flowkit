<?php

namespace MightyMango\Flowkit\Engine;

use MightyMango\Flowkit\Bricks\Core\ModelEvent;

/**
 * The lookup key stored in `flows.trigger_key` for a live flow, so the
 * dispatcher only loads flows that could match an event.
 *
 * A `core.model-event` trigger is keyed by brick, model class and event,
 * e.g. `core.model-event:App\Models\Learner:changed`. Every other trigger
 * is keyed by its brick key alone. The key only narrows the query: the
 * dispatcher still validates and matches the full settings.
 */
final class TriggerKey
{
    /**
     * @param  array<string, mixed>|null  $trigger  a definition's `trigger`: brick and settings
     */
    public static function fromTrigger(?array $trigger): ?string
    {
        $brick = $trigger['brick'] ?? null;

        if (! is_string($brick) || $brick === '') {
            return null;
        }

        if ($brick !== ModelEvent::$key) {
            return $brick;
        }

        $settings = is_array($trigger['settings'] ?? null) ? $trigger['settings'] : [];
        $model = $settings['model'] ?? null;
        $event = $settings['event'] ?? null;

        // Settings this malformed never match a model event, so key by brick only.
        if (! is_string($model) || ! is_string($event) || $model === '' || $event === '') {
            return $brick;
        }

        return self::modelEvent($model, $event);
    }

    /**
     * @param  array<string, mixed>|null  $definition  a flow version's definition
     */
    public static function fromDefinition(?array $definition): ?string
    {
        $trigger = $definition['trigger'] ?? null;

        return self::fromTrigger(is_array($trigger) ? $trigger : null);
    }

    public static function modelEvent(string $model, string $event): string
    {
        return ModelEvent::$key.':'.ltrim($model, '\\').':'.$event;
    }
}
