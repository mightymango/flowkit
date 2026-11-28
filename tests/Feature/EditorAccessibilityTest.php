<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Livewire\FlowList;
use MightyMango\Flowkit\Models\Flow;

function a11yEditor()
{
    return Livewire::test(FlowEditor::class, ['flow' => Flow::create(['key' => 'a11y', 'name' => 'Accessible'])]);
}

it('moves focus to the settings when a step is selected or added', function () {
    a11yEditor()
        ->call('setTrigger', 'core.manual')
        ->assertDispatched('flowkit-focus-settings')
        ->call('addStep', 'core.wait')
        ->assertDispatched('flowkit-focus-settings')
        ->assertSeeHtml('x-on:flowkit-focus-settings.window')
        ->assertSeeHtml('data-flowkit-settings');
});

it('uses one main landmark, from the layout, and labels the editor regions', function () {
    a11yEditor()
        ->call('setTrigger', 'core.manual')
        ->assertDontSeeHtml('<main')
        ->assertSeeHtml('<section class="flex-1 overflow-auto p-8" aria-label="Flow steps">')
        ->assertSeeHtml('<aside aria-label="Step settings"');

    expect(file_get_contents(__DIR__.'/../../resources/views/app/layouts/app.blade.php'))->toContain('<main');
});

it('gives the flow list a level-one heading', function () {
    Livewire::test(FlowList::class)->assertSeeHtml('<h1 class="text-lg font-semibold">Flows</h1>');
});

it('shows the version, whether the step is valid, and a delete button', function () {
    $editor = a11yEditor()
        ->assertSee('v1')
        ->call('setTrigger', 'core.manual')
        ->assertSee('Step is valid')
        ->assertDontSee('Delete step')
        ->call('addStep', 'core.wait')
        ->assertSee('The Wait for field is required.')
        ->assertSee('Delete step');

    applySettings($editor, ['duration' => 'PT1H']);

    $editor->assertSee('Step is valid');
});

it('labels the add-step buttons and colours step badges by kind', function () {
    a11yEditor()
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.branch')
        ->assertSee('Add a step')
        ->assertSeeHtml('uppercase tracking-wide text-[var(--flowkit-accent)]')
        ->assertSeeHtml('uppercase tracking-wide text-[var(--flowkit-warning)]');
});

it('keeps its default colours readable', function () {
    $css = file_get_contents(__DIR__.'/../../resources/css/flowkit.css');

    expect($css)->toContain('--flowkit-success: #15803d;')
        ->toContain('--flowkit-warning: #b45309;')
        ->toContain('--flowkit-accent: #818cf8;')
        ->toContain('--flowkit-accent-contrast: #0f172a;');
});
