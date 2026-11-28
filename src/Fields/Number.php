<?php

namespace MightyMango\Flowkit\Fields;

use MightyMango\Flowkit\Fields\Rules\PlaceholderOr;

/**
 * A number, or a single `{{ placeholder }}` that resolves to one when the
 * flow runs. The editor accepts the placeholder; the runner checks the
 * resolved value against the numeric rules.
 */
class Number extends Field
{
    protected bool $integer = false;

    protected int|float|null $min = null;

    protected int|float|null $max = null;

    public function component(): string
    {
        return 'flowkit::field.number';
    }

    public function integer(bool $integer = true): static
    {
        $this->integer = $integer;

        return $this;
    }

    public function min(int|float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function getEditorRules(): array
    {
        if (! $this->supportsVariables) {
            return $this->getRules();
        }

        return [
            $this->required ? 'required' : 'nullable',
            new PlaceholderOr($this->numericRules()),
            ...$this->rules,
        ];
    }

    protected function baseRules(): array
    {
        return $this->numericRules();
    }

    /** @return list<string> */
    private function numericRules(): array
    {
        return array_values(array_filter([
            $this->integer ? 'integer' : 'numeric',
            $this->min === null ? null : "min:{$this->min}",
            $this->max === null ? null : "max:{$this->max}",
        ]));
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'integer' => $this->integer,
            'min' => $this->min,
            'max' => $this->max,
        ];
    }
}
