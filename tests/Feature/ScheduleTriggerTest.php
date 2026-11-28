<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use MightyMango\Flowkit\Bricks\Core\Schedule;
use MightyMango\Flowkit\Enums\FlowStatus;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\TriggerDispatcher;

function weeklyDigest(array $settings = ['cron' => '0 9 * * 1'], string $key = 'weekly-digest'): Flow
{
    return Flowkit::define($key)
        ->name('Weekly digest')
        ->trigger('core.schedule', $settings)
        ->step('s1', 'core.set-variable', ['value' => 'Digest for {{ schedule.scheduled_at }}'])
        ->save();
}

function scheduledRunsOf(Flow $flow): int
{
    return FlowRun::whereIn('flow_version_id', $flow->versions()->pluck('id'))->count();
}

// Monday 2 March 2026.
const MONDAY_9AM = '2026-03-02 09:00:00';

it('is a registered core trigger', function () {
    expect(Flowkit::bricks()->all('trigger'))->toHaveKey('core.schedule', Schedule::class);
});

it('starts a flow whose cron is due', function () {
    $flow = weeklyDigest();
    $this->travelTo(Carbon::parse(MONDAY_9AM)->addSeconds(25));

    $this->artisan('flowkit:tick')->expectsOutputToContain('Started 1 scheduled flow.')->assertSuccessful();

    $run = FlowRun::sole();

    expect(scheduledRunsOf($flow))->toBe(1)
        ->and($run->payload['schedule'])->toBe(['scheduled_at' => '2026-03-02T09:00:00+00:00', 'timezone' => 'UTC'])
        ->and($run->refresh()->step('s1')->output['value'])->toBe('Digest for 2026-03-02T09:00:00+00:00');
});

it('does not start a flow whose cron is not due', function () {
    $flow = weeklyDigest();

    $this->travelTo(Carbon::parse(MONDAY_9AM)->addMinute());
    $this->artisan('flowkit:tick')->assertSuccessful();

    $this->travelTo(Carbon::parse(MONDAY_9AM)->addDay());
    $this->artisan('flowkit:tick')->assertSuccessful();

    expect(scheduledRunsOf($flow))->toBe(0);
});

it('evaluates the cron in the chosen time zone', function () {
    // 9am in London is 8am UTC in British Summer Time.
    $flow = weeklyDigest(['cron' => '0 9 * * *', 'timezone' => 'Europe/London']);

    $this->travelTo('2026-07-01 09:00:00');
    app(TriggerDispatcher::class)->schedule();
    expect(scheduledRunsOf($flow))->toBe(0);

    $this->travelTo('2026-07-01 08:00:00');
    app(TriggerDispatcher::class)->schedule();
    expect(scheduledRunsOf($flow))->toBe(1)
        ->and(FlowRun::sole()->payload['schedule'])
        ->toBe(['scheduled_at' => '2026-07-01T09:00:00+01:00', 'timezone' => 'Europe/London']);
});

it('ignores paused flows', function () {
    $flow = weeklyDigest();
    $flow->update(['paused' => true]);
    $this->travelTo(MONDAY_9AM);

    $this->artisan('flowkit:tick')->assertSuccessful();

    expect(scheduledRunsOf($flow))->toBe(0);
});

it('ignores flows that are not live', function () {
    $flow = Flow::create(['key' => 'draft-digest', 'name' => 'Draft digest']);
    $flow->draft()->update(['definition' => [
        'trigger' => ['brick' => 'core.schedule', 'settings' => ['cron' => '* * * * *']],
        'steps' => [],
    ]]);
    $this->travelTo(MONDAY_9AM);

    $this->artisan('flowkit:tick')->assertSuccessful();

    expect($flow->refresh()->status)->toBe(FlowStatus::Draft)
        ->and(scheduledRunsOf($flow))->toBe(0);
});

it('ignores flows with other triggers', function () {
    Flowkit::define('manual')->trigger('core.manual')->step('s1', 'core.set-variable', ['value' => 'x'])->save();
    $this->travelTo(MONDAY_9AM);

    $this->artisan('flowkit:tick')->expectsOutputToContain('Started 0 scheduled flows.');

    expect(FlowRun::count())->toBe(0);
});

it('rejects an invalid cron expression', function (mixed $cron) {
    $rules = app(Schedule::class)->rules();

    expect(Validator::make(['cron' => $cron], $rules)->fails())->toBeTrue();
})->with(['not a cron', '61 * * * *', '* * *', '', null]);

it('accepts valid cron expressions and time zones', function (string $cron) {
    $rules = app(Schedule::class)->rules();

    expect(Validator::make(['cron' => $cron, 'timezone' => 'Europe/London'], $rules)->passes())->toBeTrue();
})->with(['0 9 * * 1', '*/15 * * * *', '@daily']);

it('rejects an unknown time zone', function () {
    $rules = app(Schedule::class)->rules();

    expect(Validator::make(['cron' => '0 9 * * 1', 'timezone' => 'Mars/Olympus'], $rules)->fails())->toBeTrue();
});

it('validates the cron in the editor', function () {
    $flow = Flow::create(['key' => 'digest', 'name' => 'Digest']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.schedule');

    applySettings($editor, ['cron' => 'every monday'])->assertHasErrors('settings.cron');
    applySettings($editor, ['cron' => '0 9 * * 1'])->assertHasNoErrors();

    $editor->assertSet('trigger.settings.cron', '0 9 * * 1');
});

it('skips a stored flow whose cron is invalid', function () {
    // Code-first flows don't validate settings on save, so the tick must.
    $flow = weeklyDigest(['cron' => 'every monday']);
    $this->travelTo(MONDAY_9AM);

    $this->artisan('flowkit:tick')->assertSuccessful();

    expect(scheduledRunsOf($flow))->toBe(0);
});

it('starts only one run when ticked twice in the same minute', function () {
    $flow = weeklyDigest(['cron' => '* * * * *']);

    $this->travelTo(Carbon::parse(MONDAY_9AM)->addSeconds(5));
    $this->artisan('flowkit:tick')->assertSuccessful();

    $this->travelTo(Carbon::parse(MONDAY_9AM)->addSeconds(50));
    $this->artisan('flowkit:tick')->expectsOutputToContain('Started 0 scheduled flows.');

    expect(scheduledRunsOf($flow))->toBe(1);

    $this->travelTo(Carbon::parse(MONDAY_9AM)->addMinute());
    $this->artisan('flowkit:tick')->assertSuccessful();

    expect(scheduledRunsOf($flow))->toBe(2);
});

it('records the claimed minute so a second tick loses the claim', function () {
    $flow = weeklyDigest();
    $this->travelTo(MONDAY_9AM);

    $dispatcher = app(TriggerDispatcher::class);

    expect($dispatcher->schedule())->toBe(1)
        ->and($dispatcher->schedule(now()))->toBe(0)
        ->and($flow->refresh()->getRawOriginal('last_scheduled_at'))->toBe(MONDAY_9AM)
        ->and(scheduledRunsOf($flow))->toBe(1);
});
