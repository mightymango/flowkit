<?php

namespace MightyMango\Flowkit\Rules;

use Closure;
use Cron\CronExpression as Cron;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A standard five-field cron expression, e.g. `0 9 * * 1`, or a macro such as
 * `@daily`.
 */
class CronExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Cron::isValidExpression(trim($value))) {
            $fail('The :attribute must be a valid cron expression, such as "0 9 * * 1".');
        }
    }
}
