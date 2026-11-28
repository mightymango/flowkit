<?php

use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Models\Flow;

function polishEditor()
{
    return Livewire::test(FlowEditor::class, ['flow' => Flow::create(['key' => 'polish', 'name' => 'Polish'])]);
}

it('undoes changes one at a time', function () {
    $editor = polishEditor()
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait');

    applySettings($editor, ['duration' => 'P1D']);
    $editor->call('addStep', 'core.set-variable');

    expect($editor->get('steps'))->toHaveCount(2);

    $editor->call('undo');
    expect($editor->get('steps'))->toHaveCount(1)
        ->and($editor->get('steps')[0]['settings'])->toBe(['duration' => 'P1D']);

    $editor->call('undo');
    expect($editor->get('steps')[0]['settings'])->toBe([]);

    $editor->call('undo')->call('undo');
    expect($editor->get('trigger'))->toBeNull();

    $editor->call('undo')->assertSet('trigger', null);
});

it('keeps at most twenty changes to undo', function () {
    $editor = polishEditor()->call('setTrigger', 'core.manual');

    foreach (range(1, 25) as $i) {
        $editor->call('addStep', 'core.set-variable');
    }

    expect($editor->get('history'))->toHaveCount(FlowEditor::HISTORY_LIMIT);
});

it('marks unsaved changes until the draft is saved', function () {
    $editor = polishEditor()
        ->assertSet('dirty', false)
        ->call('setTrigger', 'core.manual')
        ->assertSet('dirty', true)
        ->assertSee('Unsaved changes')
        ->call('save')
        ->assertSet('dirty', false)
        ->assertDontSee('Unsaved changes');

    $editor->assertSeeHtml('x-on:beforeunload.window="if ($wire.dirty)');
});

it('does not let the browser change the tree directly', function () {
    polishEditor()->set('steps', [['id' => 'x', 'brick' => 'core.wait', 'settings' => ['duration' => 'nonsense']]]);
})->throws(CannotUpdateLockedPropertyException::class);

it('marks steps with missing or invalid settings on the canvas', function () {
    $editor = polishEditor()
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait')
        ->assertSee(['Needs attention', 'The Wait for field is required.'])
        ->call('select', null)
        ->assertSeeHtml('transition border-[var(--flowkit-danger)]');

    applySettings($editor, ['duration' => 'P1D'], $editor->get('steps')[0]['id']);

    $editor->assertDontSee('Needs attention');
});

it('refuses to publish while a step needs attention', function () {
    $flow = Flow::create(['key' => 'unfinished', 'name' => 'Unfinished']);

    Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait')
        ->call('publish')
        ->assertSet('errorsList', ['Wait: The Wait for field is required.']);

    expect($flow->refresh()->live_version_id)->toBeNull();
});

it('has a toolbar with undo, test and run history', function () {
    polishEditor()
        ->assertSeeHtml('role="toolbar"')
        ->assertSeeHtml('wire:click="undo" disabled')
        ->call('setTrigger', 'core.manual')
        ->assertDontSeeHtml('wire:click="undo" disabled')
        ->assertSeeHtml('wire:click="openTest"')
        ->assertSee('Run history');
});

it('shows brick descriptions and a search box in the add-step picker', function () {
    polishEditor()
        ->call('setTrigger', 'core.manual')
        ->assertSeeHtml('type="search"')
        ->assertSee('Email a user or an address.')
        ->assertSee('No steps match.');
});
