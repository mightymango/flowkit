<?php

namespace MightyMango\Flowkit\Bricks\Core;

use MightyMango\Flowkit\Bricks\Trigger;
use MightyMango\Flowkit\Fields\Select;
use MightyMango\Flowkit\Fields\Text;

/**
 * Fires when a model using HasFlowTriggers is created, updated or deleted.
 * The model's attributes are available under its snake-cased class name,
 * e.g. `employee.department`.
 */
class ModelEvent extends Trigger
{
    public static string $key = 'core.model-event';

    public static string $label = 'When a record changes';

    public static string $group = 'Core';

    public function fields(): array
    {
        return [
            Text::make('model')->label('Model class')->placeholder('App\\Models\\Employee')->required(),
            Select::make('event')->label('Event')->options([
                'created' => 'Created',
                'updated' => 'Updated',
                'deleted' => 'Deleted',
            ])->required(),
        ];
    }
}
