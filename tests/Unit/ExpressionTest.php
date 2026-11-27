<?php

use MightyMango\Flowkit\Engine\Context;
use MightyMango\Flowkit\Engine\Expression;
use Symfony\Component\ExpressionLanguage\SyntaxError;

beforeEach(function () {
    $this->expression = new Expression;
    $this->context = new Context(['employee' => ['name' => 'Ana', 'department' => 'Delivery', 'skills' => ['php', 'sql']]]);
});

it('evaluates conditions with dot access on arrays', function () {
    expect($this->expression->test("employee.department == 'Delivery'", $this->context))->toBeTrue()
        ->and($this->expression->test("employee.department == 'Sales'", $this->context))->toBeFalse()
        ->and($this->expression->evaluate('employee.skills[1]', $this->context))->toBe('sql');
});

it('renders templated strings', function () {
    expect($this->expression->render('Welcome {{ employee.name }} to {{ employee.department }}', $this->context))
        ->toBe('Welcome Ana to Delivery');
});

it('keeps the type of a lone placeholder', function () {
    expect($this->expression->render('{{ employee.skills }}', $this->context))->toBe(['php', 'sql']);
});

it('resolves nested settings', function () {
    expect($this->expression->resolve(['a' => ['b' => 'Hi {{ employee.name }}'], 'c' => 3], $this->context))
        ->toBe(['a' => ['b' => 'Hi Ana'], 'c' => 3]);
});

it('registers no functions', function () {
    $this->expression->evaluate("constant('PHP_VERSION')", $this->context);
})->throws(SyntaxError::class);
