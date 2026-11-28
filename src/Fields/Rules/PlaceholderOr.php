<?php

namespace MightyMango\Flowkit\Fields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use MightyMango\Flowkit\Engine\Expression;

/**
 * An editor rule for a field that takes variables: a single
 * `{{ placeholder }}` passes, because it only gets its value when the flow
 * runs; anything else must pass the field's own rules, which the runner
 * applies to the resolved value.
 */
class PlaceholderOr implements ValidationRule
{
    /** @param  list<mixed>  $rules */
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
