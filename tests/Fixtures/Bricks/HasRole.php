<?php

namespace MightyMango\Flowkit\Tests\Fixtures\Bricks;

use MightyMango\Flowkit\Bricks\Condition;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Fields\Text;

class HasRole extends Condition
{
    public static string $key = 'test.has-role';

    public static string $label = 'Employee has role';

    public function fields(): array
    {
        return [
            Text::make('role')->required(),
        ];
    }

    public function check(Context $context, array $settings): bool
    {
        return $context->get('employee.role') === $settings['role'];
    }
}
