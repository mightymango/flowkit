<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Livewire\FlowList;
use MightyMango\Flowkit\Livewire\StepSettings;
use MightyMango\Flowkit\Models\Flow;

it('lists flows', function () {
    Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    Livewire::test(FlowList::class)->assertSee('Onboarding');
});

it('builds, configures and publishes a flow', function () {
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->assertSee('Choose a trigger')
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.branch')
        ->assertSee('If / otherwise');

    $branchId = $editor->get('steps')[0]['id'];

    applySettings($editor, ['when' => "employee.department == 'Delivery'"])->assertHasNoErrors();

    $editor->call('addStep', 'core.set-variable', $branchId, 'yes');
    applySettings($editor, ['name' => 'greeting', 'value' => 'Welcome {{ employee.name }}'])->assertHasNoErrors();

    $editor
        ->assertSee('Set a variable')
        ->call('publish')
        ->assertSet('errorsList', []);

    $definition = $flow->refresh()->liveVersion->definition;

    expect($definition['trigger']['brick'])->toBe('core.manual')
        ->and($definition['steps'][0]['settings']['when'])->toBe("employee.department == 'Delivery'")
        ->and($definition['steps'][0]['branches']['yes'][0]['brick'])->toBe('core.set-variable');
});

it('validates step settings server-side', function () {
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait');

    applySettings($editor, ['duration' => 'next week'])
        ->assertHasErrors('settings.duration')
        ->assertNotDispatched(StepSettings::APPLIED);

    expect($editor->get('steps')[0]['settings'])->toBe([]);
});

it('validates settings again when the editor stores them', function () {
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait');
    $id = $editor->get('steps')[0]['id'];

    // A forged event from the browser, skipping the panel's validation.
    $editor->dispatch(StepSettings::APPLIED, stepId: $id, settings: ['duration' => 'next week']);
    expect($editor->get('steps')[0]['settings'])->toBe([]);

    $editor->dispatch(StepSettings::APPLIED, stepId: $id, settings: ['duration' => 'P1D', 'extra' => 'dropped']);
    expect($editor->get('steps')[0]['settings'])->toBe(['duration' => 'P1D']);
});

it('renders the settings panel for the selected step', function () {
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait')
        ->assertSeeLivewire(StepSettings::class);
});

it('lets an app swap the settings panel', function () {
    expect(config('flowkit.components'))->toBeArray()
        ->and(MightyMango\Flowkit\FlowkitServiceProvider::COMPONENTS['step-settings'])->toBe(StepSettings::class);
});

it('removes steps', function () {
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait');

    $editor->call('removeStep', $editor->get('steps')[0]['id'])->assertSet('steps', []);
});
