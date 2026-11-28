<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use MightyMango\Flowkit\Bricks\TriggerVariables;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Fields\SettingsForm;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Livewire\StepSettings;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\Assign;
use MightyMango\Flowkit\Tests\Fixtures\Models\Employee;

beforeEach(function () {
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('department')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
    });

    $this->trigger = ['brick' => 'core.model-event', 'settings' => ['model' => Employee::class, 'event' => 'changed', 'attribute' => 'status']];
});

function assignForm(?array $trigger)
{
    return SettingsForm::for(app(Assign::class), TriggerVariables::for($trigger));
}

it('lists a trigger\'s variables with their types', function () {
    expect(TriggerVariables::for($this->trigger))->toBe([
        ['path' => 'employee.id', 'label' => 'Employee', 'type' => 'employee'],
        ['path' => 'changes.status.from', 'label' => 'Previous value', 'type' => null],
        ['path' => 'changes.status.to', 'label' => 'New value', 'type' => null],
    ])->and(TriggerVariables::for(null))->toBe([]);
});

it('offers the trigger\'s variables of the asked-for type in a select', function () {
    $who = collect(assignForm($this->trigger)->fields([]))->firstWhere('name', 'who');

    expect($who['options'])->toBe(['{{ employee.id }}' => 'Employee']);
});

it('only accepts one of those variables in the editor', function () {
    expect(assignForm($this->trigger)->validator(['who' => '{{ employee.id }}'])->passes())->toBeTrue()
        ->and(assignForm($this->trigger)->validator(['who' => '{{ changes.status.to }}'])->fails())->toBeTrue()
        ->and(assignForm(null)->validator(['who' => '{{ employee.id }}'])->fails())->toBeTrue();
});

it('searches only the declared columns and scope, escaping wildcards', function () {
    Employee::create(['name' => 'Ana', 'department' => 'Delivery']);
    Employee::create(['name' => 'Ben', 'department' => 'Finance']);
    Employee::create(['name' => 'Ana Left', 'status' => 'left']);
    Employee::create(['name' => '100% Cat']);

    $panel = Livewire::test(StepSettings::class, ['stepId' => 's1', 'brick' => 'test.assign', 'trigger' => $this->trigger]);

    expect(collect($panel->instance()->searchRecords('mentor_id', 'an'))->pluck('label')->all())->toBe(['Ana', 'Ben'])
        ->and($panel->instance()->searchRecords('mentor_id', 'deliv'))->toBe([['value' => 1, 'label' => 'Ana']])
        ->and($panel->instance()->searchRecords('mentor_id', '%'))->toBe([['value' => 4, 'label' => '100% Cat']])
        ->and($panel->instance()->searchRecords('mentor_id', '  '))->toBe([]);
});

it('refuses to search a field that is not a model picker', function () {
    Livewire::test(StepSettings::class, ['stepId' => 's1', 'brick' => 'test.assign'])
        ->call('searchRecords', 'who', 'a')
        ->assertStatus(422);
});

it('checks that the picked record exists', function () {
    $ana = Employee::create(['name' => 'Ana']);

    expect(assignForm($this->trigger)->validator(['mentor_id' => $ana->id])->passes())->toBeTrue()
        ->and(assignForm($this->trigger)->validator(['mentor_id' => 999])->fails())->toBeTrue()
        ->and(assignForm($this->trigger)->validator(['mentor_id' => '{{ employee.id }}'])->fails())->toBeTrue()
        ->and(assignForm($this->trigger)->validator(['buddy_id' => '{{ employee.id }}'])->passes())->toBeTrue();
});

it('checks the resolved record when the flow runs', function () {
    $ana = Employee::create(['name' => 'Ana']);
    Flowkit::define('assign')->trigger('core.manual')->step('s1', 'test.assign', ['buddy_id' => '{{ buddy }}'])->save();

    expect(Flowkit::run('assign', ['buddy' => $ana->id])->status->value)->toBe('completed')
        ->and(Flowkit::run('assign', ['buddy' => 999])->status->value)->toBe('failed');
});

it('shows the picked record by name, in the panel and on the card', function () {
    $ana = Employee::create(['name' => 'Ana']);
    $flow = Flow::create(['key' => 'assign', 'name' => 'Assign']);

    Livewire::test(StepSettings::class, ['stepId' => 's1', 'brick' => 'test.assign', 'settings' => ['mentor_id' => $ana->id]])
        ->assertSeeHtml('label: \'Ana\'')
        ->assertSeeHtml('role="combobox"');

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->call('setTrigger', 'core.manual')
        ->call('addStep', 'test.assign');

    applySettings($editor, ['mentor_id' => $ana->id])->assertHasNoErrors();

    $editor->assertSeeHtml('text-[var(--flowkit-muted)]">Ana</span>');
});
