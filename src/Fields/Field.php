<?php

namespace MightyMango\Flowkit\Fields;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * A settings field declared by a brick. Each field type maps to an anonymous
 * Blade component, e.g. Text renders `flowkit::field.text`. A new field type
 * is a subclass plus a component, which can be published like any other.
 */
abstract class Field implements Arrayable
{
    protected string $label;

    protected ?string $placeholder = null;

    protected ?string $help = null;

    protected bool $required = false;

    protected array $rules = [];

    protected bool $supportsVariables = false;

    protected ?string $variableType = null;

    protected mixed $default = null;

    final public function __construct(public readonly string $name)
    {
        $this->label = Str::headline($name);
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    /** The anonymous Blade component that renders this field. */
    abstract public function component(): string;

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function rules(array|string $rules): static
    {
        $this->rules = array_merge($this->rules, (array) $rules);

        return $this;
    }

    /** Allow `{{ variable }}` placeholders and show the variable chips. */
    public function supportsVariables(bool $supports = true): static
    {
        $this->supportsVariables = $supports;

        return $this;
    }

    /** Offer trigger variables of a given type, e.g. 'user', as options. */
    public function variables(string $type): static
    {
        $this->variableType = $type;
        $this->supportsVariables = true;

        return $this;
    }

    public function getRules(): array
    {
        return array_merge([$this->required ? 'required' : 'nullable'], $this->baseRules(), $this->rules);
    }

    protected function baseRules(): array
    {
        return [];
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'component' => $this->component(),
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
            'required' => $this->required,
            'default' => $this->default,
            'supportsVariables' => $this->supportsVariables,
            'variableType' => $this->variableType,
        ];
    }
}
