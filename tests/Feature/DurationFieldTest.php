<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Bricks\Core\Wait;
use MightyMango\Flowkit\Fields\Duration;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Livewire\StepSettings;
use MightyMango\Flowkit\Models\Flow;

it('describes a duration in words', function (string $iso, string $words) {
    expect(Duration::describe($iso))->toBe($words);
})->with([
    ['PT30M', '30 minutes'],
    ['PT1H', '1 hour'],
    ['P7D', '7 days'],
    ['P2W', '2 weeks'],
    ['P1Y2M', '1 year, 2 months'],
    ['P1DT2H30M', '1 day, 2 hours, 30 minutes'],
    ['PT45S', '45 seconds'],
]);

it('describes a duration in Welsh', function () {
    app()->setLocale('cy');

    expect(Duration::describe('P7D'))->toBe('7 diwrnod')
        ->and(Duration::describe('PT2H'))->toBe('2 awr')
        ->and(Duration::describe('P2Y'))->toBe('2 flynedd');
});

it('does not describe an invalid duration', function () {
    expect(Duration::describe('next week'))->toBeNull()
        ->and(Duration::describe('P'))->toBeNull();
});

it('summarises a wait step in words on its card', function () {
    expect(app(Wait::class)->summary(['duration' => 'P7D']))->toBe('7 days');

    $flow = Flow::create(['key' => 'reminder', 'name' => 'Reminder']);
    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'core.wait');

    applySettings($editor, ['duration' => 'P7D']);

    $editor->assertSeeHtml('text-[var(--flowkit-muted)]">7 days</span>');
});

it('summarises a select by its option label', function () {
    $flow = Flow::create(['key' => 'reminder', 'name' => 'Reminder']);
    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.model-event');

    applySettings($editor, ['model' => 'App\\Models\\Employee', 'event' => 'created']);

    $editor->assertSee('App\\Models\\Employee · Created');
});

it('renders a number and a unit picker that stores ISO 8601', function () {
    Livewire::test(StepSettings::class, ['stepId' => 's1', 'brick' => 'core.wait', 'settings' => ['duration' => 'P7D']])
        ->assertSeeHtml('x-model="amount"')
        ->assertSeeHtml('<option value="weeks">Weeks</option>')
        ->assertSeeHtml('<option value="other">Other (ISO 8601)</option>')
        ->assertSet('settings.duration', 'P7D');
});
