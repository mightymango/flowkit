<?php

namespace MightyMango\Flowkit\Tests\Fixtures\Bricks;

use MightyMango\Flowkit\Bricks\Action;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Result;
use MightyMango\Flowkit\Fields\ModelPicker;
use MightyMango\Flowkit\Fields\Select;
use MightyMango\Flowkit\Tests\Fixtures\Models\Employee;

/** An action with a variable select and a model picker, for testing them. */
class Assign extends Action
{
    public static string $key = 'test.assign';

    public static string $label = 'Assign';

    public function fields(): array
    {
        return [
            Select::make('who')->variables('employee'),
            ModelPicker::make('mentor_id')->model(Employee::class)->searchUsing(['name', 'department'])->display('name')
                ->scope(fn ($query) => $query->where('status', 'active')),
            ModelPicker::make('buddy_id')->model(Employee::class)->searchUsing('name')->display('name')->supportsVariables(),
        ];
    }

    public function handle(Context $context, array $settings): Result
    {
        return Result::ok($settings);
    }
}
