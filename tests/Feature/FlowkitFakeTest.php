<?php

use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\Models\FlowRunStep;
use MightyMango\Flowkit\Testing\FlowkitFake;
use MightyMango\Flowkit\TriggerDispatcher;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\Flaky;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\SendMessage;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function () {
    Flowkit::define('welcome')->trigger('core.manual')
        ->step('s1', 'test.send-message', ['to' => 'lead', 'message' => 'Hi {{ name }}'])
        ->step('s2', 'core.set-variable', ['value' => 'done'])
        ->save();
});

it('swaps the facade and the container binding for a fake', function () {
    $fake = Flowkit::fake();

    expect($fake)->toBeInstanceOf(FlowkitFake::class)
        ->and(Flowkit::getFacadeRoot())->toBe($fake)
        ->and(app(\MightyMango\Flowkit\Flowkit::class))->toBe($fake);
});

it('records starts without running them', function () {
    Flowkit::fake();

    $run = Flowkit::start('welcome', ['name' => 'Ana']);

    expect($run->refresh()->status)->toBe(RunStatus::Pending)
        ->and(FlowRunStep::count())->toBe(0)
        ->and(SendMessage::$sent)->toBeEmpty();

    Flowkit::assertStarted('welcome');
    Flowkit::assertStarted('welcome', fn (array $payload) => $payload['name'] === 'Ana');
    Flowkit::assertNotStarted('welcome', fn (array $payload) => $payload['name'] === 'Bo');
    Flowkit::assertStartedTimes('welcome', 1);
    Flowkit::assertActionNotRan('test.send-message');
});

it('records test runs as starts', function () {
    Flowkit::fake();

    Flowkit::test('welcome', ['name' => 'Ana']);

    Flowkit::assertStarted('welcome', fn (array $payload, FlowRun $run) => $run->dry_run);
});

it('records flows started by triggers', function () {
    app(TriggerDispatcher::class); // resolved before the fake, holding the real Flowkit
    Flowkit::fake();

    expect(app(TriggerDispatcher::class)->fire('core.manual', ['name' => 'Ana']))->toBe(1);

    Flowkit::assertStarted('welcome');
    expect(SendMessage::$sent)->toBeEmpty();
});

it('asserts nothing started', function () {
    Flowkit::fake();

    Flowkit::assertNothingStarted();
    Flowkit::assertNotStarted('welcome');

    Flowkit::start('welcome', ['name' => 'Ana']);

    expect(fn () => Flowkit::assertNothingStarted())->toThrow(AssertionFailedError::class)
        ->and(fn () => Flowkit::assertNotStarted('welcome'))->toThrow(AssertionFailedError::class)
        ->and(fn () => Flowkit::assertStarted('other'))->toThrow(AssertionFailedError::class, 'The expected flow [other] was not started.');
});

it('records the actions of runs that execute', function () {
    Flowkit::fake();

    runFlow('welcome', ['name' => 'Ana'])->assertCompleted();

    Flowkit::assertStarted('welcome');
    Flowkit::assertActionRan('test.send-message');
    Flowkit::assertActionRan('test.send-message', fn (array $input, array $output) => $input['message'] === 'Hi Ana' && $output['message_id'] === 1);
    Flowkit::assertActionNotRan('test.send-message', fn (array $input) => $input['to'] === 'someone-else');
    Flowkit::assertActionRan('core.set-variable', fn (array $input) => $input['value'] === 'done');
    Flowkit::assertActionRanTimes('test.send-message', 1);

    expect(fn () => Flowkit::assertActionNotRan('test.send-message'))->toThrow(AssertionFailedError::class);
});

it('does not count unsafe actions in a dry run', function () {
    Flowkit::fake();

    runFlow('welcome', ['name' => 'Ana'], dryRun: true)->assertCompleted();

    Flowkit::assertActionNotRan('test.send-message');
    Flowkit::assertActionRan('core.set-variable');
});

it('does not count an action whose settings failed validation', function () {
    Flowkit::define('invalid')->trigger('core.manual')
        ->step('s1', 'test.send-message', ['to' => 'lead'])
        ->save();
    Flowkit::fake();

    runFlow('invalid')->assertFailed()->assertStepFailed('s1');

    Flowkit::assertActionNotRan('test.send-message');
});

it('counts each attempt at an action that is retried', function () {
    Flaky::$failures = 1;
    Flowkit::define('flaky')->trigger('core.manual')->step('s1', 'test.flaky', [])->save();
    Flowkit::fake();

    runFlow('flaky')->assertCompleted();

    Flowkit::assertActionRanTimes('test.flaky', 2);
});

it('ignores actions that ran before the fake', function () {
    Flowkit::run('welcome', ['name' => 'Ana']);

    Flowkit::fake();

    Flowkit::assertActionNotRan('test.send-message');
});

it('can be faked twice without wrapping a fake', function () {
    Flowkit::fake();
    $fake = Flowkit::fake();

    expect($fake->real())->not->toBeInstanceOf(FlowkitFake::class);
});

it('records a retry without running it', function () {
    config(['flowkit.retries.tries' => 1]);
    Flaky::$failures = 1;
    Flowkit::define('flaky')->trigger('core.manual')->step('s1', 'test.flaky', [])->save();
    $run = Flowkit::run('flaky');
    $other = Flowkit::run('flaky');

    Flowkit::fake();
    Flowkit::assertNothingRetried();

    Flowkit::retry($run);

    Flowkit::assertRetried($run);
    Flowkit::assertNotRetried($other);
    expect(Flaky::$calls)->toBe(2)
        ->and($run->refresh()->status)->toBe(RunStatus::Pending)
        ->and(Flowkit::retried())->toHaveCount(1)
        ->and(fn () => Flowkit::assertNothingRetried())->toThrow(AssertionFailedError::class)
        ->and(fn () => Flowkit::assertRetried($other))->toThrow(AssertionFailedError::class);
});

it('still refuses to retry a run that has not failed under the fake', function () {
    $run = Flowkit::run('welcome', ['name' => 'Ana']);

    Flowkit::fake();

    expect(fn () => Flowkit::retry($run))->toThrow(RuntimeException::class);
    Flowkit::assertNothingRetried();
});

it('records a retry from the command line under the fake', function () {
    config(['flowkit.retries.tries' => 1]);
    Flaky::$failures = 1;
    Flowkit::define('flaky')->trigger('core.manual')->step('s1', 'test.flaky', [])->save();
    $run = Flowkit::run('flaky');

    Flowkit::fake();

    $this->artisan('flowkit:retry', ['run' => $run->id])->assertSuccessful();

    Flowkit::assertRetried($run);
    expect(Flaky::$calls)->toBe(1);
});

