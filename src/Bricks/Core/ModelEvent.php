<?php

namespace MightyMango\Flowkit\Bricks\Core;

use MightyMango\Flowkit\Bricks\Trigger;
use MightyMango\Flowkit\Fields\Select;
use MightyMango\Flowkit\Fields\Text;

/**
 * Fires when a model using HasFlowTriggers is created, updated or deleted,
 * or when a watched attribute changes. The model's attributes are available
 * under its snake-cased class name, e.g. `employee.department`. A change
 * also puts `changes.<attribute>.from` and `changes.<attribute>.to` in the
 * context. Leave from or to empty to match any value.
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
                'changed' => 'An attribute changed',
            ])->required(),
            Text::make('attribute')->label('Attribute')->placeholder('status')
                ->help('Only for "An attribute changed". The model must watch it in $flowTriggers.')
                ->rules(['required_if:event,changed', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/']),
            Text::make('from')->label('From value')->placeholder('Any value'),
            Text::make('to')->label('To value')->placeholder('Any value'),
        ];
    }
}
