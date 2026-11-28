<?php

namespace MightyMango\Flowkit\Fields;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use MightyMango\Flowkit\Bricks\Brick;
use MightyMango\Flowkit\Bricks\Core\Branch;

/**
 * A brick's settings form: the fields the editor shows and the server-side
 * validation of what's entered. Shared by the step settings panel and the
 * editor, which validates again before storing settings in the tree.
 */
class SettingsForm
{
    public function __construct(private Brick $brick) {}

    public static function for(Brick $brick): self
    {
        return new self($brick);
    }

    /**
     * The fields as arrays for the field components. A branch that uses a
     * Condition brick also shows that condition's fields, stored under
     * `condition_settings`.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(array $settings): array
    {
        $fields = [];

        foreach ($this->fieldObjects($settings) as $name => $field) {
            $fields[] = ['name' => $name] + $field->toArray();
        }

        return $fields;
    }

    /**
     * Validate the settings on their own, exactly as the runner does, so
     * rules that refer to other settings (e.g. required_without) resolve.
     */
    public function validator(array $settings): ValidatorContract
    {
        return Validator::make(
            $settings,
            $this->editorRules($settings),
            attributes: array_column($this->fields($settings), 'label', 'name'),
        );
    }

    /**
     * The brick's rules, with each field's editor rules in place of its
     * runner rules, since placeholders aren't resolved yet. A rule the brick
     * set itself (by overriding rules()) is kept as it is.
     */
    private function editorRules(array $settings): array
    {
        $rules = $this->brick->rulesFor($settings);

        foreach ($this->fieldObjects($settings) as $name => $field) {
            if (($rules[$name] ?? null) == $field->getRules()) {
                $rules[$name] = $field->getEditorRules();
            }
        }

        return $rules;
    }

    /** @return array<string, Field> */
    private function fieldObjects(array $settings): array
    {
        $fields = [];

        foreach ($this->brick->fields() as $field) {
            $fields[$field->name] = $field;
        }

        if ($this->brick instanceof Branch) {
            foreach ($this->brick->conditionFields($settings) as $field) {
                $fields["condition_settings.{$field->name}"] = $field;
            }
        }

        return $fields;
    }
}
