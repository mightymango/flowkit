<?php

use Illuminate\Support\Facades\Queue;
use MightyMango\Flowkit\Engine\FlowBuilder;
use MightyMango\Flowkit\Engine\Runner;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Jobs\RunFlow;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\Flaky;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\FlakySend;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\SendMessage;

function flakyFlow(string $brick = 'test.flaky')
{
    return Flowkit::define('flaky')->trigger('core.manual')
        ->step('s1', 'core.set-variable', ['value' => 'before'])
        ->step('s2', $brick, [])
        ->step('s3', 'core.set-variable', ['value' => 'after {{ steps.s1.value }}'])
        ->save();
}

it('retries a safe action that throws, recording each attempt', function () {
    Flaky::$failures = 2;
    flakyFlow();

    $run = Flowkit::start('flaky')->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and(Flaky::$calls)->toBe(3)
        ->and($run->steps->map(fn ($s) => [$s->step_id, $s->attempt, $s->status])->all())->toBe([
            ['s1', 1, RunStatus::Completed],
            ['s2', 1, RunStatus::Failed],
            ['s2', 2, RunStatus::Failed],
            ['s2', 3, RunStatus::Completed],
            ['s3', 1, RunStatus::Completed],
        ])
        ->and($run->step('s3')->output)->toBe(['value' => 'after before']);
});

it('fails the run once the attempts are used up', function () {
    config(['flowkit.retries.tries' => 2]);
    Flaky::$failures = 5;
    flakyFlow();

    $run = Flowkit::start('flaky')->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->finished_at)->not->toBeNull()
        ->and(Flaky::$calls)->toBe(2)
        ->and($run->step('s2')->attempt)->toBe(2)
        ->and($run->step('s2')->error)->toBe('Service unavailable')
        ->and($run->step('s3'))->toBeNull();
});

it('queues the retry after the configured backoff', function () {
    config(['flowkit.retries.backoff' => [30, 120]]);
    Flaky::$failures = 1;
    flakyFlow();

    Queue::fake();
    $run = Flowkit::start('flaky');
    Queue::assertPushed(RunFlow::class, 1);

    (new RunFlow($run))->handle(app(Runner::class));

    expect($run->refresh()->status)->toBe(RunStatus::Pending)
        ->and($run->finished_at)->toBeNull();

    Queue::assertPushed(RunFlow::class, fn (RunFlow $job) => $job->run->is($run)
        && $job->delay !== null
        && now()->diffInSeconds($job->delay, true) >= 29);
});

it('uses the last backoff value for later attempts', function () {
    config(['flowkit.retries.backoff' => [5, 20]]);

    $brick = new Flaky;

    expect($brick->backoff(1))->toBe(5)
        ->and($brick->backoff(2))->toBe(20)
        ->and($brick->backoff(7))->toBe(20);
});

it('gives an unsafe action one attempt', function () {
    Flaky::$failures = 1;
    flakyFlow('test.flaky-send');

    $run = Flowkit::start('flaky')->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and(FlakySend::$calls)->toBe(1)
        ->and($run->steps()->where('step_id', 's2')->count())->toBe(1);
});

it('retries an unsafe action that uses its idempotency key', function () {
    FlakySend::$usesIdempotencyKey = true;
    Flaky::$failures = 1;
    flakyFlow('test.flaky-send');

    $run = Flowkit::start('flaky')->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and(FlakySend::$calls)->toBe(2);
});

it('does not retry a step whose settings are invalid', function () {
    Flowkit::define('broken')->trigger('core.manual')
        ->step('s1', 'core.set-variable', [])
        ->save();

    $run = Flowkit::start('broken')->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->steps)->toHaveCount(1);
});

it('resumes a failed run from the failed step without repeating earlier steps', function () {
    config(['flowkit.retries.tries' => 1]);
    Flaky::$failures = 1;

    Flowkit::define('branchy')->trigger('core.manual')
        ->branch('s1', "employee.role == 'manager'",
            yes: fn (FlowBuilder $b) => $b->step('s2', 'test.send-message', ['to' => 'hr', 'message' => 'Hi'])
                ->step('s3', 'test.flaky', []),
        )
        ->step('s4', 'core.set-variable', ['value' => 'done'])
        ->save();

    $run = Flowkit::start('branchy', ['employee' => ['role' => 'manager']])->refresh();
    expect($run->status)->toBe(RunStatus::Failed);

    // Taking the other branch now would be wrong: the recorded choice holds.
    $run->forceFill(['payload' => ['employee' => ['role' => 'engineer']]])->save();

    Flowkit::retry($run);
    $run->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and(SendMessage::$sent)->toHaveCount(1)
        ->and($run->steps->map(fn ($s) => "{$s->step_id}#{$s->attempt}")->all())->toBe(['s1#1', 's2#1', 's3#1', 's3#2', 's4#1']);
});

it('only retries failed runs', function () {
    flakyFlow();
    $run = Flowkit::start('flaky')->refresh();

    Flowkit::retry($run);
})->throws(RuntimeException::class);

it('retries a failed run from the command line', function () {
    config(['flowkit.retries.tries' => 1]);
    Flaky::$failures = 1;
    flakyFlow();
    $run = Flowkit::start('flaky')->refresh();

    $this->artisan('flowkit:retry', ['run' => $run->id])
        ->expectsOutputToContain('from step [s2]')
        ->assertSuccessful();

    expect($run->refresh()->status)->toBe(RunStatus::Completed);
});

it('asks before retrying a step that is not safe to repeat', function () {
    Flaky::$failures = 1;
    flakyFlow('test.flaky-send');
    $run = Flowkit::start('flaky')->refresh();

    $this->artisan('flowkit:retry', ['run' => $run->id])
        ->expectsConfirmation("Step [s2] (Flaky send) isn't safe to repeat and may already have done its work. Retry it anyway?", 'no')
        ->assertFailed();

    expect($run->refresh()->status)->toBe(RunStatus::Failed);

    $this->artisan('flowkit:retry', ['run' => $run->id, '--force' => true])->assertSuccessful();

    expect($run->refresh()->status)->toBe(RunStatus::Completed);
});

it('refuses to retry a run that has not failed', function () {
    flakyFlow();
    $run = Flowkit::start('flaky')->refresh();

    $this->artisan('flowkit:retry', ['run' => $run->id])->assertFailed();
    $this->artisan('flowkit:retry', ['run' => 999])->assertFailed();
});
