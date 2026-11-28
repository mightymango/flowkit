<?php

namespace MightyMango\Flowkit\Fields;

use Closure;
use Illuminate\Validation\Rule;

class Select extends Field
{
    protected array|Closure $options = [];

    /**
     * @param  array<string, string>|Closure(): array<string, string>  $options  value => label
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options instanceof Closure ? ($this->options)() : $this->options;
    }

    public function component(): string
    {
        return 'flowkit::field.select';
    }

    protected function baseRules(): array
    {
        // A select fed by trigger variables holds a `{{ placeholder }}`, so
        // only fixed option lists are checked here.
        return $this->variableType === null && $this->getOptions()
            ? [Rule::in(array_keys($this->getOptions()))]
            : [];
    }

    /** A fixed option reads as its label. */
    public function summarise(mixed $value): ?string
    {
        $options = $this->variableType === null ? $this->getOptions() : [];

        return is_scalar($value) && isset($options[$value]) ? (string) __($options[$value]) : parent::summarise($value);
    }

    public function toArray(): array
    {
        return parent::toArray() + ['options' => array_map(fn ($label) => __($label), $this->getOptions())];
    }
}
