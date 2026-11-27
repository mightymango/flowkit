<?php

namespace Act\Flowkit\Bricks\Core;

use Act\Flowkit\Bricks\Brick;
use Act\Flowkit\Engine\Context;
use Act\Flowkit\Engine\Expression;
use Act\Flowkit\Fields\Text;

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
