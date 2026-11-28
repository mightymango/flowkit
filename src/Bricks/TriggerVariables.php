<?php

namespace MightyMango\Flowkit\Bricks;

/**
 * The variables a flow's trigger puts into the run, as a flat list the
 * editor can offer: in selects that ask for a type, and as variable chips.
 */
class TriggerVariables
{
    /**
     * @param  array{brick?: string, settings?: array}|null  $trigger  the flow's trigger node
     * @return list<array{path: string, label: string, type: string|null}>
     */
    public static function for(?array $trigger): array
    {
        $registry = app(BrickRegistry::class);

        if (! $trigger || ! $registry->has($trigger['brick'] ?? '')) {
            return [];
        }

        $brick = $registry->resolve($trigger['brick']);

        if (! $brick instanceof Trigger) {
            return [];
        }

        $variables = [];

        foreach ($brick->variablesFor($trigger['settings'] ?? []) as $path => $variable) {
            $variables[] = [
                'path' => $path,
                'label' => (string) __(is_array($variable) ? $variable['label'] : $variable),
                'type' => is_array($variable) ? ($variable['type'] ?? null) : null,
            ];
        }

        return $variables;
    }
}
