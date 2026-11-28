<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use MightyMango\Flowkit\Enums\FlowStatus;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Livewire\FlowList;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Tests\Fixtures\User;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->timestamps();
    });

    $this->ana = User::create(['name' => 'Ana Owen']);
});

function liveFlow(string $key, string $name): Flow
{
    return Flowkit::define($key)->name($name)->trigger('core.manual')
        ->step('s1', 'core.set-variable', ['value' => 'x'])
        ->save();
}

it('shows each flow\'s owner and last real run', function () {
    $flow = liveFlow('onboarding', 'Onboarding');
    $flow->owner()->associate($this->ana)->save();
    Flowkit::run($flow, []);
    Flowkit::test($flow->key, []);
    liveFlow('quiet', 'Quiet');

    Livewire::test(FlowList::class)
        ->assertSee(['Owner: Ana Owen', 'Last run', '(Completed)', 'Owner: nobody', 'Not run yet']);
});

it('searches by name, case-insensitively and literally', function () {
    liveFlow('onboarding', 'New starter onboarding');
    liveFlow('leavers', 'Leavers');
    liveFlow('pct', '100% done');

    Livewire::test(FlowList::class)
        ->set('search', 'ONBOARD')
        ->assertSee('New starter onboarding')
        ->assertDontSee('Leavers')
        ->set('search', '%')
        ->assertSee('100% done')
        ->assertDontSee('Leavers')
        ->set('search', 'zzz')
        ->assertSee('No flows match.');
});

it('filters by status and hides archived flows by default', function () {
    liveFlow('live', 'Live one');
    $paused = liveFlow('paused', 'Paused one');
    $paused->update(['paused' => true]);
    $archived = liveFlow('archived', 'Archived one');
    $archived->update(['status' => FlowStatus::Archived]);
    Flow::create(['key' => 'draft', 'name' => 'Draft one']);

    Livewire::test(FlowList::class)
        ->assertSee(['Live one', 'Paused one', 'Draft one'])
        ->assertDontSee('Archived one')
        ->set('status', 'paused')
        ->assertSee('Paused one')
        ->assertDontSee(['Live one', 'Draft one'])
        ->set('status', 'draft')
        ->assertSee('Draft one')
        ->assertDontSee('Live one')
        ->set('status', 'archived')
        ->assertSee('Archived one')
        ->assertDontSee('Live one');
});

it('pauses and resumes a flow', function () {
    $flow = liveFlow('onboarding', 'Onboarding');

    Livewire::test(FlowList::class)->call('pause', $flow->id);
    expect($flow->refresh()->paused)->toBeTrue()->and($flow->isRunnable())->toBeFalse();

    Livewire::test(FlowList::class)->call('resume', $flow->id);
    expect($flow->refresh()->isRunnable())->toBeTrue();
});

it('archives a flow so it stops running, and restores it', function () {
    $flow = liveFlow('onboarding', 'Onboarding');
    $draftOnly = Flow::create(['key' => 'draft', 'name' => 'Draft']);

    Livewire::test(FlowList::class)->call('archive', $flow->id)->call('archive', $draftOnly->id);
    expect($flow->refresh()->status)->toBe(FlowStatus::Archived)->and($flow->isRunnable())->toBeFalse();

    Livewire::test(FlowList::class)->call('restore', $flow->id)->call('restore', $draftOnly->id);
    expect($flow->refresh()->status)->toBe(FlowStatus::Live)
        ->and($draftOnly->refresh()->status)->toBe(FlowStatus::Draft);
});

it('duplicates a flow into a new draft owned by you', function () {
    $flow = liveFlow('onboarding', 'Onboarding');
    $this->actingAs($this->ana);

    Livewire::test(FlowList::class)->call('duplicate', $flow->id)->assertRedirect();

    $copy = Flow::where('name', 'Copy of Onboarding')->sole();

    expect($copy->key)->toBe('copy-of-onboarding')
        ->and($copy->status)->toBe(FlowStatus::Draft)
        ->and($copy->owner->is($this->ana))->toBeTrue()
        ->and($copy->draft()->definition)->toBe($flow->liveVersion->definition)
        ->and($copy->live_version_id)->toBeNull();
});

it('renames a flow and edits its description', function () {
    $flow = liveFlow('onboarding', 'Onboarding');

    Livewire::test(FlowList::class)
        ->call('edit', $flow->id)
        ->assertSet('editName', 'Onboarding')
        ->set('editName', '')
        ->call('saveDetails')
        ->assertHasErrors('editName')
        ->set('editName', 'New starters')
        ->set('editDescription', 'Welcomes everyone who joins.')
        ->call('saveDetails')
        ->assertHasNoErrors()
        ->assertSet('editingId', null)
        ->assertSee(['New starters', 'Welcomes everyone who joins.']);

    expect($flow->refresh()->name)->toBe('New starters')->and($flow->key)->toBe('onboarding');
});

it('offers the actions in an accessible menu', function () {
    $flow = liveFlow('onboarding', 'Onboarding');

    Livewire::test(FlowList::class)
        ->assertSeeHtml('aria-label="Actions for Onboarding"')
        ->assertSeeHtml("wire:click=\"pause({$flow->id})\"")
        ->assertSeeHtml("wire:click=\"duplicate({$flow->id})\"")
        ->assertSeeHtml("wire:click=\"archive({$flow->id})\"");
});
