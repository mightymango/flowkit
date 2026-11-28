<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Engine\FlowBuilder;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Exceptions\InvalidDefinition;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Models\Flow;

function managerFlow()
{
    return Flowkit::define('managers')->trigger('core.manual')
        ->branchOn('s1', 'test.has-role', ['role' => 'manager'],
            yes: fn (FlowBuilder $b) => $b->step('s2', 'core.set-variable', ['value' => 'manager']),
            otherwise: fn (FlowBuilder $b) => $b->step('s3', 'core.set-variable', ['value' => 'not a manager']),
        )
        ->save();
}

it('branches on a condition brick', function () {
    managerFlow();

    $yes = Flowkit::start('managers', ['employee' => ['role' => 'manager']])->refresh();
    $no = Flowkit::start('managers', ['employee' => ['role' => 'engineer']])->refresh();

    expect($yes->status)->toBe(RunStatus::Completed)
        ->and($yes->steps->pluck('step_id')->all())->toBe(['s1', 's2'])
        ->and($yes->step('s1')->output)->toBe(['branch' => 'yes'])
        ->and($no->steps->pluck('step_id')->all())->toBe(['s1', 's3']);
});

it('resolves variables in the condition settings', function () {
    Flowkit::define('templated')->trigger('core.manual')
        ->branchOn('s1', 'test.has-role', ['role' => '{{ wanted }}'],
            yes: fn (FlowBuilder $b) => $b->step('s2', 'core.set-variable', ['value' => 'matched']),
        )
        ->save();

    $run = Flowkit::start('templated', ['wanted' => 'lead', 'employee' => ['role' => 'lead']])->refresh();

    expect($run->step('s1')->output)->toBe(['branch' => 'yes']);
});

it('validates the condition settings when the run reaches the branch', function () {
    Flowkit::define('missing-role')->trigger('core.manual')
        ->branchOn('s1', 'test.has-role', [])
        ->save();

    $run = Flowkit::start('missing-role')->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->step('s1')->error)->toContain('role');
});

it('still branches on an expression', function () {
    Flowkit::define('expression')->trigger('core.manual')
        ->branch('s1', "employee.role == 'manager'",
            yes: fn (FlowBuilder $b) => $b->step('s2', 'core.set-variable', ['value' => 'manager']),
        )
        ->save();

    $run = Flowkit::start('expression', ['employee' => ['role' => 'manager']])->refresh();

    expect($run->steps->pluck('step_id')->all())->toBe(['s1', 's2']);
});

it('fails a branch with neither an expression nor a condition', function () {
    Flowkit::define('empty-branch')->trigger('core.manual')
        ->step('s1', 'core.branch', [])
        ->save();

    $run = Flowkit::start('empty-branch')->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->step('s1')->error)->toContain('when');
});

it('rejects a branch whose condition is not a condition brick', function () {
    $definition = Definition::fromArray([
        'trigger' => ['brick' => 'core.manual'],
        'steps' => [['id' => 's1', 'brick' => 'core.branch', 'settings' => ['condition' => 'core.set-variable']]],
    ]);

    expect(fn () => $definition->validate())->toThrow(InvalidDefinition::class);
});

it('stops the run when a standalone condition fails', function () {
    Flowkit::define('filter')->trigger('core.manual')
        ->step('s1', 'test.has-role', ['role' => 'manager'])
        ->step('s2', 'core.set-variable', ['value' => 'after'])
        ->save();

    $stopped = Flowkit::start('filter', ['employee' => ['role' => 'engineer']])->refresh();
    $passed = Flowkit::start('filter', ['employee' => ['role' => 'manager']])->refresh();

    expect($stopped->status)->toBe(RunStatus::Completed)
        ->and($stopped->steps->pluck('step_id')->all())->toBe(['s1'])
        ->and($stopped->step('s1')->output)->toBe(['passed' => false, 'stopped' => true])
        ->and($passed->steps->pluck('step_id')->all())->toBe(['s1', 's2'])
        ->and($passed->step('s1')->output)->toBe(['passed' => true]);
});

it('stops the whole run when a condition inside a branch fails', function () {
    Flowkit::define('nested-filter')->trigger('core.manual')
        ->branch('s1', 'true',
            yes: fn (FlowBuilder $b) => $b->step('s2', 'test.has-role', ['role' => 'manager']),
        )
        ->step('s3', 'core.set-variable', ['value' => 'after'])
        ->save();

    $run = Flowkit::start('nested-filter', ['employee' => ['role' => 'engineer']])->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps->pluck('step_id')->all())->toBe(['s1', 's2']);
});

it('picks a condition for a branch in the editor and shows its fields', function () {
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.branch')
        ->assertSee('Employee has role');

    settingsPanel($editor)
        ->set('settings.condition', 'test.has-role')
        ->assertSeeHtml('settings.condition_settings.role')
        ->call('apply')
        ->assertHasErrors('settings.condition_settings.role');

    applySettings($editor, ['condition' => 'test.has-role', 'condition_settings.role' => 'manager'])->assertHasNoErrors();

    $editor->call('publish')
        ->assertSet('errorsList', []);

    expect($flow->refresh()->liveVersion->definition['steps'][0]['settings'])->toBe([
        'condition' => 'test.has-role',
        'condition_settings' => ['role' => 'manager'],
    ]);
});

it('rejects an unknown condition in the editor', function () {
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.branch');

    applySettings($editor, ['condition' => 'core.set-variable'])->assertHasErrors('settings.condition');
});
