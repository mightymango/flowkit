<?php

namespace MightyMango\Flowkit\Bricks;

/**
 * Starts a run. Declares the variables it puts into the context; that list
 * drives the variable chips in the editor.
 *
 * TODO (phase 3): restrict expressions to the declared variables only.
 */
abstract class Trigger extends Brick
{
    /**
     * Variables this trigger provides, as dot paths mapped to a label, or
     * to a label and a type that fields can ask for with ->variables():
     *
     *   ['employee.name' => 'Employee name',
     *    'employee.manager_id' => ['label' => 'Manager', 'type' => 'user']]
     *
     * @return array<string, string|array{label: string, type?: string}>
     */
    public function variables(): array
    {
        return [];
    }

    /**
     * The variables for a trigger with these settings. Override when they
     * depend on the settings, e.g. on which model the trigger watches.
     *
     * @return array<string, string|array{label: string, type?: string}>
     */
    public function variablesFor(array $settings): array
    {
        return $this->variables();
    }

    /**
     * Shape the raw trigger payload into the run's starting context.
     */
    public function payload(mixed $event): array
    {
        return is_array($event) ? $event : (array) $event;
    }

    public static function type(): string
    {
        return 'trigger';
    }
}
