<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\SendMessage;

function draftEditor(): array
{
    $flow = Flow::create(['key' => 'digest', 'name' => 'Digest']);
    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.schedule');
    applySettings($editor, ['cron' => '0 9 * * 1']);

    $editor->call('addStep', 'test.send-message');
    applySettings($editor, ['to' => 'team', 'message' => 'Digest for {{ schedule.timezone }}']);

    return [$flow, $editor];
}

it('pre-fills the trigger data from the trigger\'s variables', function () {
    [, $editor] = draftEditor();

    $editor->call('openTest')->assertSet('testing', true);

    expect(json_decode($editor->get('testPayload'), true))->toBe(['schedule' => ['scheduled_at' => '', 'timezone' => '']]);
});

it('tests the unsaved draft as a dry run and shows the result', function () {
    [$flow, $editor] = draftEditor();

    $editor->call('openTest')
        ->set('testPayload', '{"schedule": {"timezone": "Europe/London"}}')
        ->call('runTest')
        ->assertHasNoErrors();

    $run = $flow->runs()->sole();

    expect($run->dry_run)->toBeTrue()
        ->and($run->status)->toBe(RunStatus::Completed)
        ->and($run->version->isPublished())->toBeFalse()
        ->and($run->version->definition['steps'])->toHaveCount(1)
        ->and(SendMessage::$sent)->toBe([]);

    $editor->assertSet('testRunId', $run->id)
        ->assertSee(['Run '.$run->id, 'Send a message', 'Test run']);
});

it('rejects trigger data that is not a JSON object', function (string $json) {
    [$flow, $editor] = draftEditor();

    $editor->call('openTest')->set('testPayload', $json)->call('runTest')->assertHasErrors('testPayload');

    expect($flow->runs()->count())->toBe(0);
})->with(['not json', '[1, 2]', '"text"']);

it('reports an invalid draft instead of testing it', function () {
    $flow = Flow::create(['key' => 'broken', 'name' => 'Broken']);
    $flow->draft()->update(['definition' => [
        'trigger' => ['brick' => 'core.manual', 'settings' => []],
        'steps' => [['id' => 's1', 'brick' => 'no.such-brick', 'settings' => []]],
    ]]);

    Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('openTest')
        ->call('runTest')
        ->assertNotSet('errorsList', []);

    expect($flow->runs()->count())->toBe(0);
});

it('fills in the trigger data of a recent run of this flow only', function () {
    [$flow, $editor] = draftEditor();
    $editor->call('publish');
    $real = Flowkit::run($flow->refresh(), ['schedule' => ['timezone' => 'Europe/Paris']]);
    $other = Flowkit::define('other')->trigger('core.manual')->step('s1', 'core.set-variable', ['value' => 'x'])->save();
    $foreign = Flowkit::run($other, ['secret' => 'yes']);

    $editor->call('openTest')
        ->assertSeeHtml("wire:click=\"useRun({$real->id})\"")
        ->call('useRun', $real->id);

    expect(json_decode($editor->get('testPayload'), true))->toBe(['schedule' => ['timezone' => 'Europe/Paris']]);

    $editor->call('useRun', $foreign->id)->assertStatus(404);
});

it('never runs an unpublished version for real', function () {
    $flow = Flow::create(['key' => 'draft-only', 'name' => 'Draft only']);

    expect(fn () => Flowkit::run($flow->draft()->flow, [], dryRun: false))->toThrow(RuntimeException::class);
});
