<?php

namespace MightyMango\Flowkit\Bricks\Core;

use MightyMango\Flowkit\Bricks\Brick;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Expression;
use MightyMango\Flowkit\Fields\Text;

/**
 * If/otherwise. Evaluates `when` and continues down `yes` or `otherwise`.
 */
class Branch extends Brick
{
    public static string $key = 'core.branch';

    public static string $label = 'If / otherwise';

    public static string $group = 'Logic';

    public function __construct(private Expression $expression) {}

    public function fields(): array
    {
        return [
            Text::make('when')
                ->label('Condition')
                ->placeholder("employee.department == 'Delivery'")
                ->required(),
        ];
    }

    /**
     * @return list<string>
     */
    public function branches(): array
    {
        return ['yes', 'otherwise'];
    }

    public function decide(Context $context, array $settings): string
    {
        return $this->expression->test($settings['when'], $context) ? 'yes' : 'otherwise';
    }

    public static function type(): string
    {
        return 'branch';
    }
}
