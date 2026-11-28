<?php

namespace MightyMango\Flowkit\Bricks\Core;

use LogicException;
use MightyMango\Flowkit\Bricks\Brick;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Condition;
use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Expression;
use MightyMango\Flowkit\Fields\Field;
use MightyMango\Flowkit\Fields\Select;
use MightyMango\Flowkit\Fields\Text;

/**
 * If/otherwise. Tests either an expression (`when`) or a Condition brick
 * (`condition`, with that brick's own settings under `condition_settings`),
 * then continues down `yes` or `otherwise`.
 *
 *   { "brick": "core.branch", "settings": { "when": "employee.department == 'Delivery'" } }
 *   { "brick": "core.branch", "settings": { "condition": "app.is-manager", "condition_settings": { ... } } }
 */
class Branch extends Brick
{
    public static string $key = 'core.branch';

    public static string $label = 'If / otherwise';

    public static string $group = 'Logic';

    public function __construct(
        private Expression $expression,
        private BrickRegistry $bricks,
    ) {}

    public function outputs(): array
    {
        return ['branch' => 'Branch taken'];
    }

    public function fields(): array
    {
        return [
            Select::make('condition')
                ->label('Check')
                ->placeholder('An expression')
                ->options(fn () => $this->conditionOptions())
                ->live()
                ->help('Pick a ready-made check, or leave this empty and write an expression.'),
            Text::make('when')
                ->label('Expression')
                ->placeholder("employee.department == 'Delivery'")
                ->rules('required_without:condition'),
        ];
    }

    /**
     * Adds the chosen Condition brick's own rules, under `condition_settings`.
     */
    public function rulesFor(array $settings): array
    {
        $rules = $this->rules();

        foreach ($this->conditionFields($settings) as $field) {
            $rules["condition_settings.{$field->name}"] = $field->getRules();
        }

        return $rules;
    }

    /**
     * The chosen Condition brick's fields, which the editor shows beneath
     * the branch's own and stores under `condition_settings`.
     *
     * @return list<Field>
     */
    public function conditionFields(array $settings): array
    {
        $key = $settings['condition'] ?? null;

        // An unknown key is reported by the `condition` field's own rule.
        if (! is_string($key) || ! array_key_exists($key, $this->bricks->all('condition'))) {
            return [];
        }

        return $this->bricks->resolve($key)->fields();
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
        $condition = $this->condition($settings);

        $passed = $condition
            ? $condition->check($context, $settings['condition_settings'] ?? [])
            : $this->expression->test($settings['when'], $context);

        return $passed ? 'yes' : 'otherwise';
    }

    public static function type(): string
    {
        return 'branch';
    }

    private function condition(array $settings): ?Condition
    {
        $key = $settings['condition'] ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        $brick = $this->bricks->resolve($key);

        if (! $brick instanceof Condition) {
            throw new LogicException("Brick [{$key}] is not a condition.");
        }

        return $brick;
    }

    /**
     * @return array<string, string>
     */
    private function conditionOptions(): array
    {
        return array_map(fn (string $class) => $class::$label, $this->bricks->all('condition'));
    }
}
