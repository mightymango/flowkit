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
        $fields = array_map(fn (Field $field) => $field->toArray(), $this->brick->fields());

        if ($this->brick instanceof Branch) {
            foreach ($this->brick->conditionFields($settings) as $field) {
                $fields[] = ['name' => "condition_settings.{$field->name}"] + $field->toArray();
            }
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
            $this->brick->rulesFor($settings),
            attributes: array_column($this->fields($settings), 'label', 'name'),
        );
    }
}
