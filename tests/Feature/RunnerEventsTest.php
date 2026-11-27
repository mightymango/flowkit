<?php

use MightyMango\Flowkit\Engine\FlowBuilder;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Events\RunFinished;
use MightyMango\Flowkit\Events\RunStarted;
use MightyMango\Flowkit\Events\StepFinished;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\Flaky;
use Illuminate\Support\Facades\Event;

it('fires events as a run starts, takes each step and finishes', function () {
    Event::fake([RunStarted::class, StepFinished::class, RunFinished::class]);

    Flowkit::define('events')->trigger('core.manual')
        ->branch('s1', 'true', yes: fn (FlowBuilder $b) => $b->step('s2', 'core.set-variable', ['value' => 'a']))
        ->step('s3', 'test.has-role', ['role' => 'manager'])
        ->save();

    $run = Flowkit::run('events', ['employee' => ['role' => 'manager']]);

    Event::assertDispatched(RunStarted::class, fn (RunStarted $e) => $e->runId === $run->id
        && $e->correlationId === $run->correlation_id && ! $e->resumed && ! $e->dryRun);

    $steps = Event::dispatched(StepFinished::class)->map(fn ($args) => $args[0]);
    expect($steps->map(fn (StepFinished $e) => [$e->stepId, $e->brick, $e->status, $e->attempt, $e->handled])->all())->toBe([
        ['s1', 'core.branch', RunStatus::Completed, 1, true],
        ['s2', 'core.set-variable', RunStatus::Completed, 1, true],
        ['s3', 'test.has-role', RunStatus::Completed, 1, true],
    ])->and($steps->first()->stepRecordId)->toBe($run->step('s1')->id);

    Event::assertDispatched(RunFinished::class, fn (RunFinished $e) => $e->runId === $run->id && $e->status === RunStatus::Completed);
});

it('marks a step that failed validation as not handled', function () {
    Event::fake([StepFinished::class, RunFinished::class]);

    Flowkit::define('invalid')->trigger('core.manual')->step('s1', 'core.set-variable', [])->save();
    Flowkit::run('invalid');

    Event::assertDispatched(StepFinished::class, fn (StepFinished $e) => $e->status === RunStatus::Failed && ! $e->handled);
    Event::assertDispatched(RunFinished::class, fn (RunFinished $e) => $e->status === RunStatus::Failed);
});

it('marks an unsafe action replaced by its dry run as not handled', function () {
    Event::fake([StepFinished::class]);

    Flowkit::define('dry')->trigger('core.manual')
        ->step('s1', 'test.send-message', ['to' => 'lead', 'message' => 'Hi'])
        ->save();
    Flowkit::run('dry', dryRun: true);

    Event::assertDispatched(StepFinished::class, fn (StepFinished $e) => $e->brick === 'test.send-message'
        && $e->status === RunStatus::Completed && ! $e->handled);
});

it('marks an action that threw as handled, and fires again for each attempt', function () {
    Event::fake([RunStarted::class, StepFinished::class, RunFinished::class]);
    Flaky::$failures = 1;

    Flowkit::define('flaky')->trigger('core.manual')->step('s1', 'test.flaky', [])->save();
    Flowkit::start('flaky');

    $steps = Event::dispatched(StepFinished::class)->map(fn ($args) => [$args[0]->status, $args[0]->attempt, $args[0]->handled]);
    expect($steps->all())->toBe([[RunStatus::Failed, 1, true], [RunStatus::Completed, 2, true]]);

    // Started twice (the retry resumes it), finished once.
    Event::assertDispatchedTimes(RunStarted::class, 2);
    Event::assertDispatched(RunStarted::class, fn (RunStarted $e) => $e->resumed);
    Event::assertDispatchedTimes(RunFinished::class, 1);
});

it('does not fire RunFinished while a run waits', function () {
    Event::fake([RunFinished::class]);

    Flowkit::define('waits')->trigger('core.manual')->step('s1', 'core.wait', ['duration' => 'P1D'])->save();
    Flowkit::run('waits');

    Event::assertNotDispatched(RunFinished::class);
});

it('does not fire StepFinished for steps a resumed run skips', function () {
    config(['flowkit.retries.tries' => 1]);
    Flaky::$failures = 1;
    Flowkit::define('resume')->trigger('core.manual')
        ->step('s1', 'core.set-variable', ['value' => 'x'])
        ->step('s2', 'test.flaky', [])
        ->save();
    $run = Flowkit::run('resume');

    Event::fake([StepFinished::class]);
    Flowkit::retry($run);

    expect(Event::dispatched(StepFinished::class)->map(fn ($args) => $args[0]->stepId)->all())->toBe(['s2']);
});
