<?php

namespace MightyMango\Flowkit\Bricks;

use MightyMango\Flowkit\Fields\Field;

/**
 * The base of every trigger, condition and action. A brick declares its own
 * settings fields; the editor's settings panel is built from them, so a new
 * brick needs no Blade.
 */
abstract class Brick
{
    /** Unique key stored in definitions, e.g. `teams.message`. */
    public static string $key;

    public static string $label;

    /** Groups bricks in the editor's picker. */
    public static string $group = 'General';

    public static ?string $description = null;

    /**
     * @return list<Field>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * What a step of this brick puts into the run for later steps, under
     * `steps.<id>.<key>`, as key => label (or label and type, as for trigger
     * variables). The editor offers them as variable chips.
     *
     * @return array<string, string|array{label: string, type?: string}>
     */
    public function outputs(): array
    {
        return [];
    }

    /**
     * A short, readable description of a step's settings for its card, e.g.
     * "7 days" for a wait. By default each field's summary, in order.
     *
     * @param  list<array{path: string, label: string, type: string|null}>  $variables  the flow's trigger variables
     */
    public function summary(array $settings, array $variables = []): string
    {
        $parts = [];

        foreach ($this->fields() as $field) {
            if (array_key_exists($field->name, $settings) && ($part = $field->withVariables($variables)->summarise($settings[$field->name])) !== null) {
                $parts[] = $part;
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Validation rules built from the fields, keyed by setting name.
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $rules[$field->name] = $field->getRules();
        }

        return $rules;
    }

    /**
     * The rules for a given set of settings. Override when some rules depend
     * on other settings, e.g. a branch adds its chosen condition's rules.
     */
    public function rulesFor(array $settings): array
    {
        return $this->rules();
    }

    abstract public static function type(): string;

    public static function meta(): array
    {
        return [
            'key' => static::$key,
            'label' => __(static::$label),
            'group' => __(static::$group),
            'description' => static::$description === null ? null : __(static::$description),
            'type' => static::type(),
        ];
    }
}
