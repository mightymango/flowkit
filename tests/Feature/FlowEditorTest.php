<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Livewire\FlowList;
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

    $editor
        ->set('settings.when', "employee.department == 'Delivery'")
        ->call('applySettings')
        ->assertHasNoErrors()
        ->call('addStep', 'core.set-variable', $branchId, 'yes')
        ->set('settings.value', 'Welcome {{ employee.name }}')
        ->call('applySettings')
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

    Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait')
        ->set('settings.duration', 'next week')
        ->call('applySettings')
        ->assertHasErrors('settings.duration');
});

it('removes steps', function () {
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait');

    $editor->call('removeStep', $editor->get('steps')[0]['id'])->assertSet('steps', []);
});
