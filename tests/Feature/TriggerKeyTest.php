<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MightyMango\Flowkit\Engine\TriggerKey;
use MightyMango\Flowkit\Enums\FlowStatus;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\FlowkitServiceProvider;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\Tests\Fixtures\Models\Employee;
use MightyMango\Flowkit\TriggerDispatcher;

beforeEach(function () {
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('department')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
    });
});

function keyedModelFlow(string $key, array $settings): Flow
{
    return Flowkit::define($key)
        ->trigger('core.model-event', $settings)
        ->step('s1', 'core.set-variable', ['value' => 'ran'])
        ->save();
}

it('stores a model, event and brick key when a flow is published', function () {
    $flow = keyedModelFlow('leaver', ['model' => '\\'.Employee::class, 'event' => 'changed', 'attribute' => 'status']);

    expect($flow->trigger_key)->toBe('core.model-event:'.Employee::class.':changed');
});

it('keys other triggers by their brick', function () {
    $flow = Flowkit::define('manual')->trigger('core.manual')->step('s1', 'core.set-variable', ['value' => 'x'])->save();

    expect($flow->trigger_key)->toBe('core.manual');
});

it('keys a model event with malformed settings by its brick only', function () {
    expect(TriggerKey::fromTrigger(['brick' => 'core.model-event', 'settings' => ['model' => ['x'], 'event' => 'created']]))
        ->toBe('core.model-event')
        ->and(TriggerKey::fromTrigger(null))->toBeNull()
        ->and(TriggerKey::fromTrigger(['settings' => []]))->toBeNull();
});

it('updates the key when a new version is published', function () {
    keyedModelFlow('hr', ['model' => Employee::class, 'event' => 'created']);
    $flow = keyedModelFlow('hr', ['model' => Employee::class, 'event' => 'deleted']);

    expect($flow->trigger_key)->toBe('core.model-event:'.Employee::class.':deleted');
});

it('clears the key when a flow is archived or unpublished, and restores it when live again', function () {
    $flow = keyedModelFlow('hr', ['model' => Employee::class, 'event' => 'created']);
    $live = $flow->trigger_key;

    $flow->update(['status' => FlowStatus::Archived]);
    expect($flow->refresh()->trigger_key)->toBeNull();

    $flow->update(['status' => FlowStatus::Live]);
    expect($flow->refresh()->trigger_key)->toBe($live);

    $flow->update(['status' => FlowStatus::Draft, 'live_version_id' => null]);
    expect($flow->refresh()->trigger_key)->toBeNull();

    Employee::create(['name' => 'Ana']);
    expect(FlowRun::count())->toBe(0);
});

it('keeps the query count flat as unrelated live flows are added', function () {
    Flowkit::fake();
    keyedModelFlow('on-create', ['model' => Employee::class, 'event' => 'created']);

    $loaded = 0;
    Flow::retrieved(function () use (&$loaded) {
        $loaded++;
    });

    $measure = function () use (&$loaded): int {
        $loaded = 0;
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TriggerDispatcher::class)->modelEvent(new Employee(['name' => 'Ana']), 'created');
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $queries = $measure();
    $flowsLoaded = $loaded;

    foreach (range(1, 20) as $i) {
        keyedModelFlow("learner-{$i}", ['model' => 'App\\Models\\Learner', 'event' => 'created']);
        keyedModelFlow("employee-deleted-{$i}", ['model' => Employee::class, 'event' => 'deleted']);
        Flowkit::define("manual-{$i}")->trigger('core.manual')->step('s1', 'core.set-variable', ['value' => 'x'])->save();
    }

    // Without the index every live flow would be loaded and matched in PHP.
    expect($measure())->toBe($queries)
        ->and($loaded)->toBe($flowsLoaded)
        ->and($loaded)->toBeLessThan(3);

    Flowkit::assertStartedTimes('on-create', 2);
    Flowkit::assertNotStarted('learner-1');
});

it('still finds keyed flows when fired by brick key alone', function () {
    Flowkit::fake();
    keyedModelFlow('on-create', ['model' => Employee::class, 'event' => 'created']);

    $started = app(TriggerDispatcher::class)->fire('core.model-event', ['event' => 'created']);

    expect($started)->toBe(1);
});

it('backfills the key for flows that are already live', function () {
    $flow = keyedModelFlow('leaver', ['model' => Employee::class, 'event' => 'changed', 'attribute' => 'status']);
    Flowkit::define('manual')->trigger('core.manual')->step('s1', 'core.set-variable', ['value' => 'x'])->save();
    $draft = Flow::create(['key' => 'draft', 'name' => 'Draft']);
    $draft->draft();

    $migration = require FlowkitServiceProvider::MIGRATIONS_PATH.'/2026_11_28_000001_add_trigger_key_to_flows_table.php';
    $migration->down();
    $migration->up();

    expect(Flow::query()->orderBy('id')->pluck('trigger_key', 'key')->all())->toBe([
        'leaver' => 'core.model-event:'.Employee::class.':changed',
        'manual' => 'core.manual',
        'draft' => null,
    ]);

    Employee::create(['name' => 'Ana'])->update(['status' => 'leaver']);
    expect(FlowRun::count())->toBe(1);
});
