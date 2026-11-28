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

    /**
     * The options. A select that asks for a type of variable offers the
     * trigger's variables of that type, as `{{ placeholder }}` values.
     */
    public function getOptions(): array
    {
        if ($this->variableType !== null) {
            $options = [];

            foreach ($this->availableVariables as $variable) {
                if ($variable['type'] === $this->variableType) {
                    $options['{{ '.$variable['path'].' }}'] = $variable['label'];
                }
            }

            return $options;
        }

        return $this->options instanceof Closure ? ($this->options)() : $this->options;
    }

    /** In the editor, a variable select must hold one of its variables. */
    public function getEditorRules(): array
    {
        return $this->variableType === null
            ? $this->getRules()
            : [...$this->getRules(), Rule::in(array_keys($this->getOptions()))];
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
        $options = $this->getOptions();

        return is_scalar($value) && isset($options[$value]) ? (string) __($options[$value]) : parent::summarise($value);
    }

    public function toArray(): array
    {
        return parent::toArray() + ['options' => array_map(fn ($label) => __($label), $this->getOptions())];
    }
}
