<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Livewire\FlowList;
use MightyMango\Flowkit\Livewire\RunHistory;
use MightyMango\Flowkit\Models\Flow;

function codeFlow(): Flow
{
    return Flowkit::define('learner-withdrawn')->name('Learner withdrawn')->trigger('core.manual')
        ->step('s1', 'core.set-variable', ['value' => 'x'])
        ->save();
}

it('leaves hidden flows out of the list, the editor and the run history', function () {
    config(['flowkit.editor.hidden_flows' => ['learner-withdrawn']]);
    $hidden = codeFlow();
    Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    Livewire::test(FlowList::class)->assertSee('Onboarding')->assertDontSee('Learner withdrawn');
    Livewire::test(FlowEditor::class, ['flow' => $hidden])->assertNotFound();
    Livewire::test(RunHistory::class, ['flow' => $hidden])->assertNotFound();
    Livewire::test(FlowList::class)->call('archive', $hidden->id)->assertNotFound();
});

it('still runs hidden flows', function () {
    config(['flowkit.editor.hidden_flows' => ['learner-withdrawn']]);

    expect(Flowkit::run(codeFlow())->status->value)->toBe('completed');
});

it('does not offer hidden bricks in the editor, and refuses to add them', function () {
    config(['flowkit.editor.hidden_bricks' => ['core.notify', 'core.schedule']]);
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Onboarding']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->assertDontSee('On a schedule')
        ->assertSee('Run manually')
        ->call('setTrigger', 'core.manual')
        ->assertDontSee('Send an email')
        ->assertSee('Set a variable');

    $editor->call('addStep', 'core.notify')->assertStatus(422);

    Livewire::test(FlowEditor::class, ['flow' => $flow])->call('setTrigger', 'core.schedule')->assertStatus(422);
});

it('hides nothing by default', function () {
    Livewire::test(FlowEditor::class, ['flow' => Flow::create(['key' => 'f', 'name' => 'F'])])
        ->call('setTrigger', 'core.manual')
        ->assertSee('Send an email');
});
