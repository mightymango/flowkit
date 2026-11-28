<?php

namespace MightyMango\Flowkit\Bricks;

/**
 * The variables a step can use: its flow's trigger variables, plus the
 * outputs of the steps that always run before it. A step in a branch sees
 * the steps before its branch step and the branch step itself, but nothing
 * inside another branch, which may not have run.
 */
class StepVariables
{
    /**
     * @param  array{brick?: string, settings?: array}|null  $trigger
     * @param  array<int, array>  $steps  the step tree
     * @return list<array{path: string, label: string, type: string|null}>
     */
    public static function before(?array $trigger, array $steps, string $stepId): array
    {
        if ($stepId === 'trigger') {
            return [];
        }

        $earlier = self::earlierSteps($steps, $stepId, []) ?? [];

        return [...TriggerVariables::for($trigger), ...self::outputsOf($earlier)];
    }

    /**
     * The steps that run before the target, or null if it isn't in this list.
     *
     * @param  list<array>  $before
     * @return list<array>|null
     */
    private static function earlierSteps(array $steps, string $stepId, array $before): ?array
    {
        foreach (array_values($steps) as $step) {
            if ($step['id'] === $stepId) {
                return $before;
            }

            foreach ($step['branches'] ?? [] as $children) {
                $found = self::earlierSteps($children, $stepId, [...$before, $step]);

                if ($found !== null) {
                    return $found;
                }
            }

            $before[] = $step;
        }

        return null;
    }

    /**
     * @param  list<array>  $steps
     * @return list<array{path: string, label: string, type: string|null}>
     */
    private static function outputsOf(array $steps): array
    {
        $registry = app(BrickRegistry::class);
        $variables = [];

        foreach ($steps as $step) {
            if (! $registry->has($step['brick'] ?? '')) {
                continue;
            }

            $brick = $registry->resolve($step['brick']);

            foreach ($brick->outputs() as $key => $output) {
                $variables[] = [
                    'path' => "steps.{$step['id']}.{$key}",
                    'label' => __($brick::$label).': '.__(is_array($output) ? $output['label'] : $output),
                    'type' => is_array($output) ? ($output['type'] ?? null) : null,
                ];
            }
        }

        return $variables;
    }
}
