<?php

namespace Act\Flowkit\Engine;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Evaluates conditions (`employee.department == 'Delivery'`) and templated
 * strings (`Welcome {{ employee.name }}`) against a run's context.
 *
 * Expressions are sandboxed: they never go through eval or Blade, no
 * functions are registered (not even Symfony's default `constant`/`enum`),
 * and the context is passed in as plain data with no methods to call.
 */
class Expression
{
    private ExpressionLanguage $language;

    public function __construct()
    {
        $this->language = new SandboxedExpressionLanguage;
    }

    public function evaluate(string $expression, Context|array $context): mixed
    {
        return $this->language->evaluate($expression, $this->variables($context));
    }

    public function test(string $expression, Context|array $context): bool
    {
        return (bool) $this->evaluate($expression, $context);
    }

    /**
     * Replace every `{{ expression }}` in a string. A string that is just one
     * placeholder keeps the evaluated value's type.
     */
    public function render(string $template, Context|array $context): mixed
    {
        if (substr_count($template, '{{') === 1 && preg_match('/^\s*\{\{(.+)\}\}\s*$/s', $template, $match)) {
            return $this->evaluate(trim($match[1]), $context);
        }

        return preg_replace_callback(
            '/\{\{(.+?)\}\}/s',
            function (array $match) use ($context) {
                $value = $this->evaluate(trim($match[1]), $context);

                return is_scalar($value) || $value === null ? (string) $value : json_encode($value);
            },
            $template,
        );
    }

    /**
     * Render every string in a settings array, recursively.
     */
    public function resolve(array $settings, Context|array $context): array
    {
        array_walk_recursive($settings, function (&$value) use ($context) {
            if (is_string($value) && str_contains($value, '{{')) {
                $value = $this->render($value, $context);
            }
        });

        return $settings;
    }

    /**
     * Turn associative arrays into plain objects so the `a.b.c` property
     * syntax works; lists stay arrays so `items[0]` works.
     */
    private function variables(Context|array $context): array
    {
        $data = $context instanceof Context ? $context->all() : $context;

        return array_map(fn ($value) => $this->objectify($value), $data);
    }

    private function objectify(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $mapped = array_map(fn ($item) => $this->objectify($item), $value);

        return array_is_list($value) ? $mapped : (object) $mapped;
    }
}
