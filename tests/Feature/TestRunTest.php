<?php

use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Testing\TestRun;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function () {
    Flowkit::define('greet')->trigger('core.manual')
        ->step('s1', 'core.set-variable', ['value' => 'Hello {{ name }}'])
        ->save();
});

it('runs a flow synchronously, whatever the queue', function () {
    config(['queue.default' => 'database']);

    $test = runFlow('greet', ['name' => 'Ana']);

    expect($test)->toBeInstanceOf(TestRun::class);

    $test->assertCompleted()
        ->assertStepCompleted('s1')
        ->assertStepOutput('s1', 'value', 'Hello Ana');
});

it('fails with a clear message when an assertion does not hold', function () {
    $test = runFlow('greet', ['name' => 'Ana']);

    expect(fn () => $test->assertFailed())->toThrow(AssertionFailedError::class, 'Expected the run to be [failed] but it is [completed].')
        ->and(fn () => $test->assertStepCompleted('s9'))->toThrow(AssertionFailedError::class, 'Step [s9] was not reached.')
        ->and(fn () => $test->assertStepSkipped('s1'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $test->assertStepOutput('s1', 'value', 'Hello Bo'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $test->assertStepOutput('s1', 'missing', 'x'))->toThrow(AssertionFailedError::class, 'Step [s1] has no output [missing].');
});
