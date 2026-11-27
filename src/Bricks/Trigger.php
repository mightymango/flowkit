<?php

namespace Act\Flowkit\Bricks;

/**
 * Starts a run. Declares the variables it puts into the context; that list
 * drives the variable chips in the editor.
 *
 * TODO (phase 3): restrict expressions to the declared variables only.
 */
abstract class Trigger extends Brick
{
    /**
     * Variables this trigger provides, as dot paths mapped to labels,
     * e.g. ['employee.name' => 'Employee name'].
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        return [];
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
