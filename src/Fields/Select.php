<?php

namespace Act\Flowkit\Fields;

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

    public function toArray(): array
    {
        return parent::toArray() + ['options' => $this->getOptions()];
    }
}
