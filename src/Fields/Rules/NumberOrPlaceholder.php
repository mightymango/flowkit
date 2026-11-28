<?php

namespace MightyMango\Flowkit\Fields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use MightyMango\Flowkit\Engine\Expression;

/**
 * The editor's check for a Number field: a single `{{ placeholder }}`
 * passes, because it only becomes a number when the flow runs; anything
 * else must pass the numeric rules.
 */
class NumberOrPlaceholder implements ValidationRule
{
    /** @param  list<string>  $rules */
    public function __construct(private array $rules) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Expression::lonePlaceholder($value) !== null) {
            return;
        }

        // Keep `:attribute` in the message so the outer validator fills in
        // the field's label.
        $validator = Validator::make(['value' => $value], ['value' => $this->rules], attributes: ['value' => ':attribute']);

        if ($validator->fails()) {
            $fail($validator->errors()->first('value'));
        }
    }
}
