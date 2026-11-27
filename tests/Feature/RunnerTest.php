<?php

use Act\Flowkit\Engine\FlowBuilder;
use Act\Flowkit\Enums\RunStatus;
use Act\Flowkit\Facades\Flowkit;
use Act\Flowkit\Tests\Fixtures\Bricks\SendMessage;

function newStarterFlow()
{
    return Flowkit::define('new-starter')
        ->name('New starter')
        ->trigger('core.manual')
        ->branch('s1', "employee.department == 'Delivery'",
            yes: fn (FlowBuilder $b) => $b->step('s2', 'test.send-message', [
                'to' => 'delivery-lead',
                'message' => 'Welcome {{ employee.name }}',
            ]),
            otherwise: fn (FlowBuilder $b) => $b->step('s3', 'core.set-variable', ['value' => 'skipped']),
        )
        ->step('s4', 'core.set-variable', ['value' => 'Done for {{ employee.name }}'])
        ->save();
}

it('runs a code-first flow down the matching branch', function () {
    newStarterFlow();

    $run = Flowkit::start('new-starter', ['employee' => ['name' => 'Ana', 'department' => 'Delivery']])->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps->pluck('step_id')->all())->toBe(['s1', 's2', 's4'])
        ->and($run->step('s1')->output)->toBe(['branch' => 'yes'])
        ->and($run->step('s4')->output)->toBe(['value' => 'Done for Ana'])
        ->and(SendMessage::$sent)->toHaveCount(1)
        ->and(SendMessage::$sent[0]['message'])->toBe('Welcome Ana');
});

it('takes the otherwise branch', function () {
    newStarterFlow();

    $run = Flowkit::start('new-starter', ['employee' => ['name' => 'Bo', 'department' => 'Sales']])->refresh();

    expect($run->steps->pluck('step_id')->all())->toBe(['s1', 's3', 's4'])
        ->and(SendMessage::$sent)->toBeEmpty();
});

it('does not call unsafe actions in a dry run', function () {
    newStarterFlow();

    $run = Flowkit::test('new-starter', ['employee' => ['name' => 'Ana', 'department' => 'Delivery']])->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and(SendMessage::$sent)->toBeEmpty()
        ->and($run->step('s2')->output['dry_run'])->toBeTrue();
});

it('keeps runs on the version they started with', function () {
    $flow = newStarterFlow();
    $first = $flow->live_version_id;

    Flowkit::define('new-starter')->trigger('core.manual')->step('s1', 'core.set-variable', ['value' => 'v2'])->save();

    expect($flow->refresh()->live_version_id)->not->toBe($first)
        ->and($flow->versions()->count())->toBe(2)
        ->and($flow->versions()->first()->definition['steps'][0]['brick'])->toBe('core.branch');
});

it('stops and waits at a wait step', function () {
    Flowkit::define('waits')->trigger('core.manual')
        ->step('s1', 'core.wait', ['duration' => 'P7D'])
        ->step('s2', 'core.set-variable', ['value' => 'later'])
        ->save();

    $run = Flowkit::start('waits')->refresh();

    expect($run->status)->toBe(RunStatus::Waiting)
        ->and($run->steps->pluck('step_id')->all())->toBe(['s1']);
});

it('fails a step whose settings are invalid', function () {
    Flowkit::define('broken')->trigger('core.manual')
        ->step('s1', 'test.send-message', ['to' => 'someone'])
        ->save();

    $run = Flowkit::start('broken')->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->step('s1')->error)->toContain('message');
});
