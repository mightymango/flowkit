<?php

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Livewire\RunHistory;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Tests\Fixtures\User;

function historyFlow(string $key = 'reminder'): Flow
{
    return Flowkit::define($key)->name(ucfirst($key))->trigger('core.manual')
        ->step('s1', 'core.set-variable', ['value' => '{{ person.name }}'])
        ->step('s2', 'test.snooze', ['minutes' => '{{ person.minutes }}'])
        ->save();
}

it('lists a flow\'s runs, newest first, with status, version and test runs marked', function () {
    $flow = historyFlow();
    $first = Flowkit::run($flow, ['person' => ['name' => 'Ana', 'minutes' => 5]]);
    $second = Flowkit::test($flow->key, ['person' => ['name' => 'Ben', 'minutes' => 'soon']]);
    $other = Flowkit::run(historyFlow('other'), ['person' => ['name' => 'Cy', 'minutes' => 1]]);

    Livewire::test(RunHistory::class, ['flow' => $flow])
        ->assertSeeInOrder(['Test run', 'Failed', 'Version 1', 'Completed'])
        ->assertSeeHtml("wire:click=\"show({$first->id})\"")
        ->assertSeeHtml("wire:click=\"show({$second->id})\"")
        ->assertDontSeeHtml("wire:click=\"show({$other->id})\"");
});

it('shows each step of a run with its input, output, error and duration', function () {
    $flow = historyFlow();
    $run = Flowkit::run($flow, ['person' => ['name' => 'Ana', 'minutes' => 'soon']]);

    Livewire::test(RunHistory::class, ['flow' => $flow])
        ->call('show', $run->id)
        ->assertSet('run', $run->id)
        ->assertSee(['Run '.$run->id, 'Set a variable', 'Snooze', 'must be an integer'])
        ->assertSeeHtml('<summary class="cursor-pointer text-xs font-medium text-[var(--flowkit-muted)]">Input</summary>')
        ->assertSee('"value": "Ana"')
        ->assertSee(' ms');
});

it('starts with inputs and outputs collapsed', function () {
    $flow = historyFlow();
    $run = Flowkit::run($flow, ['person' => ['name' => 'Ana', 'minutes' => 5]]);

    Livewire::test(RunHistory::class, ['flow' => $flow])
        ->call('show', $run->id)
        ->assertSeeHtml('<details class="mt-2">')
        ->assertDontSeeHtml('<details class="mt-2" open');
});

it('does not show a run of another flow', function () {
    $flow = historyFlow();
    $other = Flowkit::run(historyFlow('other'), ['person' => ['name' => 'Cy', 'minutes' => 1]]);

    Livewire::test(RunHistory::class, ['flow' => $flow])
        ->call('show', $other->id)
        ->assertDontSee('Run '.$other->id)
        ->assertSee('Choose a run to see its steps.');
});

it('shows an empty state with the retention period', function () {
    config(['flowkit.retention_days' => 30]);

    Livewire::test(RunHistory::class, ['flow' => historyFlow()])
        ->assertSee(['No runs yet', 'Runs are kept for 30 days.']);
});

it('serves the history page behind the flowkit gate, linked from the list and editor', function () {
    $flow = historyFlow();
    Gate::define('use-flowkit', fn () => true);
    $this->actingAs(new User(['id' => 1]));

    $this->get(route('flowkit.runs', $flow))->assertOk()->assertSee('Runs of Reminder');
    $this->get(route('flowkit.index'))->assertSee(route('flowkit.runs', $flow));
    $this->get(route('flowkit.edit', $flow))->assertSee(route('flowkit.runs', $flow));

    Gate::define('use-flowkit', fn () => false);
    $this->get(route('flowkit.runs', $flow))->assertForbidden();
});
