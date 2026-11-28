<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Bricks\StepVariables;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Livewire\StepSettings;
use MightyMango\Flowkit\Models\Flow;

function chipTree(): array
{
    return [
        ['id' => 'a', 'brick' => 'core.set-variable', 'settings' => []],
        ['id' => 'b', 'brick' => 'core.branch', 'settings' => [], 'branches' => [
            'yes' => [
                ['id' => 'c', 'brick' => 'core.set-variable', 'settings' => []],
                ['id' => 'd', 'brick' => 'core.notify', 'settings' => []],
            ],
            'otherwise' => [
                ['id' => 'e', 'brick' => 'core.set-variable', 'settings' => []],
            ],
        ]],
        ['id' => 'f', 'brick' => 'core.set-variable', 'settings' => []],
    ];
}

function pathsBefore(string $stepId): array
{
    $trigger = ['brick' => 'core.schedule', 'settings' => ['cron' => '0 9 * * 1']];

    return array_column(StepVariables::before($trigger, chipTree(), $stepId), 'path');
}

it('offers the trigger variables and the outputs of steps that run first', function () {
    expect(pathsBefore('a'))->toBe(['schedule.scheduled_at', 'schedule.timezone'])
        ->and(pathsBefore('d'))->toBe(['schedule.scheduled_at', 'schedule.timezone', 'steps.a.value', 'steps.b.branch', 'steps.c.value']);
});

it('leaves out steps in another branch and steps after the current one', function () {
    expect(pathsBefore('e'))->toBe(['schedule.scheduled_at', 'schedule.timezone', 'steps.a.value', 'steps.b.branch'])
        ->and(pathsBefore('a'))->not->toContain('steps.f.value');
});

it('after a branch, offers only what ran whichever way it went', function () {
    expect(pathsBefore('f'))->toBe(['schedule.scheduled_at', 'schedule.timezone', 'steps.a.value', 'steps.b.branch']);
});

it('labels a step output with its brick', function () {
    $variables = StepVariables::before(null, chipTree(), 'f');

    expect($variables[0])->toBe(['path' => 'steps.a.value', 'label' => 'Set a variable: Value', 'type' => null]);
});

it('offers no variables to the trigger itself', function () {
    expect(StepVariables::before(['brick' => 'core.schedule'], chipTree(), 'trigger'))->toBe([]);
});

it('shows chips on fields that take variables, inserting placeholders', function () {
    $flow = Flow::create(['key' => 'chips', 'name' => 'Chips']);
    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.schedule')
        ->call('addStep', 'core.set-variable')
        ->call('addStep', 'core.set-variable');

    $first = $editor->get('steps')[0]['id'];

    settingsPanel($editor)
        ->assertSeeHtml('aria-label="Insert a variable"')
        ->assertSeeHtml('Set a variable: Value')
        ->assertSeeHtml("let text = '{{ steps.{$first}.value }}';")
        ->assertDontSeeHtml('echo e(')
        ->assertSeeHtml('title="schedule.scheduled_at"');
});

it('shows no chips on fields that pick a value', function () {
    Livewire::test(StepSettings::class, [
        'stepId' => 's1',
        'brick' => 'core.set-variable',
        'variables' => [['path' => 'schedule.timezone', 'label' => 'Time zone', 'type' => null]],
    ])->assertSeeHtml('aria-label="Insert a variable"');

    Livewire::test(StepSettings::class, [
        'stepId' => 's1',
        'brick' => 'test.assign',
        'variables' => [['path' => 'employee.id', 'label' => 'Employee', 'type' => 'employee']],
    ])->assertDontSeeHtml('aria-label="Insert a variable"');
});
