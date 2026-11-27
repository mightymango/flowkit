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

    abstract public static function type(): string;

    public static function meta(): array
    {
        return [
            'key' => static::$key,
            'label' => static::$label,
            'group' => static::$group,
            'description' => static::$description,
            'type' => static::type(),
        ];
    }
}
