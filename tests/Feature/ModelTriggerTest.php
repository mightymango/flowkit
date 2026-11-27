<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use MightyMango\Flowkit\Bricks\Core\ModelEvent;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\Tests\Fixtures\Models\Employee;

beforeEach(function () {
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('department')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
    });
});

function modelFlow(string $key, array $settings): void
{
    Flowkit::define($key)
        ->trigger('core.model-event', ['model' => Employee::class] + $settings)
        ->step('s1', 'core.set-variable', ['value' => 'ran'])
        ->save();
}

function runsOf(string $key)
{
    return FlowRun::query()->whereHas('version.flow', fn ($q) => $q->where('key', $key))->get();
}

it('still fires on events in the list syntax', function () {
    modelFlow('on-create', ['event' => 'created']);

    Employee::create(['name' => 'Ana', 'status' => 'active']);

    $runs = runsOf('on-create');
    expect($runs)->toHaveCount(1)
        ->and($runs->first()->payload['event'])->toBe('created')
        ->and($runs->first()->payload['employee']['name'])->toBe('Ana')
        ->and($runs->first()->payload)->not->toHaveKey('changes');
});

it('fires when a watched attribute changes, with the old and new values', function () {
    modelFlow('leaver', ['event' => 'changed', 'attribute' => 'status']);
    $employee = Employee::create(['name' => 'Ana', 'status' => 'active']);

    $employee->update(['status' => 'leaver']);

    $runs = runsOf('leaver');
    expect($runs)->toHaveCount(1)
        ->and($runs->first()->payload['event'])->toBe('changed')
        ->and($runs->first()->payload['changes'])->toBe(['status' => ['from' => 'active', 'to' => 'leaver']])
        ->and($runs->first()->payload['employee']['status'])->toBe('leaver');
});

it('does not fire when an unrelated attribute changes', function () {
    modelFlow('leaver', ['event' => 'changed', 'attribute' => 'status']);
    $employee = Employee::create(['name' => 'Ana', 'status' => 'active']);

    $employee->update(['department' => 'Delivery']);

    expect(runsOf('leaver'))->toBeEmpty();
});

it('does not fire for an attribute the model does not watch', function () {
    modelFlow('moved', ['event' => 'changed', 'attribute' => 'department']);
    $employee = Employee::create(['name' => 'Ana', 'status' => 'active']);

    $employee->update(['department' => 'Delivery']);

    expect(runsOf('moved'))->toBeEmpty();
});

it('does not fire updated triggers when the model only watches attributes', function () {
    modelFlow('on-update', ['event' => 'updated']);
    $employee = Employee::create(['name' => 'Ana', 'status' => 'active']);

    $employee->update(['status' => 'leaver']);

    expect(runsOf('on-update'))->toBeEmpty();
});

it('filters on the new value', function () {
    modelFlow('leaver', ['event' => 'changed', 'attribute' => 'status', 'to' => 'leaver']);
    $employee = Employee::create(['name' => 'Ana', 'status' => 'active']);

    $employee->update(['status' => 'on-leave']);
    expect(runsOf('leaver'))->toBeEmpty();

    $employee->update(['status' => 'leaver']);
    expect(runsOf('leaver'))->toHaveCount(1);
});

it('filters on the old value', function () {
    modelFlow('back', ['event' => 'changed', 'attribute' => 'status', 'from' => 'on-leave', 'to' => 'active']);
    $employee = Employee::create(['name' => 'Ana', 'status' => 'active']);

    $employee->update(['status' => 'leaver']);
    $employee->update(['status' => 'active']);
    expect(runsOf('back'))->toBeEmpty();

    $employee->update(['status' => 'on-leave']);
    $employee->update(['status' => 'active']);
    expect(runsOf('back'))->toHaveCount(1);
});

it('ignores a trigger whose settings are invalid', function () {
    modelFlow('no-attribute', ['event' => 'changed']);
    $employee = Employee::create(['name' => 'Ana', 'status' => 'active']);

    $employee->update(['status' => 'leaver']);

    expect(runsOf('no-attribute'))->toBeEmpty();
});

it('validates the change settings', function () {
    $rules = (new ModelEvent)->rules();
    $valid = fn (array $settings) => validator(['model' => Employee::class] + $settings, $rules)->passes();

    expect($valid(['event' => 'changed', 'attribute' => 'status', 'to' => 'leaver']))->toBeTrue()
        ->and($valid(['event' => 'created']))->toBeTrue()
        ->and($valid(['event' => 'changed']))->toBeFalse()
        ->and($valid(['event' => 'changed', 'attribute' => 'status; drop']))->toBeFalse()
        ->and($valid(['event' => 'renamed']))->toBeFalse();
});
