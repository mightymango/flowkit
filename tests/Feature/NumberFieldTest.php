<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Fields\Number;
use MightyMango\Flowkit\Fields\SettingsForm;
use MightyMango\Flowkit\Livewire\StepSettings;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\Snooze;

function snoozeForm(array $settings)
{
    return SettingsForm::for(app(Snooze::class))->validator($settings);
}

it('accepts a number within its limits in the editor', function () {
    expect(snoozeForm(['minutes' => 5])->passes())->toBeTrue()
        ->and(snoozeForm(['minutes' => '5', 'ratio' => '0.5'])->passes())->toBeTrue();
});

it('rejects anything else in the editor, naming the field', function (mixed $minutes, string $message) {
    $validator = snoozeForm(['minutes' => $minutes]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('minutes'))->toBe($message);
})->with([
    'text' => ['soon', 'The Minutes field must be an integer.'],
    'a fraction' => ['1.5', 'The Minutes field must be an integer.'],
    'too small' => [0, 'The Minutes field must be at least 1.'],
    'too big' => [61, 'The Minutes field must not be greater than 60.'],
    'two placeholders' => ['{{ a }}{{ b }}', 'The Minutes field must be an integer.'],
]);

it('accepts a single placeholder in the editor, which the runner resolves later', function () {
    expect(snoozeForm(['minutes' => '{{ reminder.minutes }}'])->passes())->toBeTrue();
});

it('does not accept a placeholder in a field without variables', function () {
    expect(snoozeForm(['minutes' => 5, 'ratio' => '{{ x }}'])->fails())->toBeTrue();
});

it('checks the resolved value when the flow runs', function () {
    Flowkit::define('snooze')->trigger('core.manual')->step('s1', 'test.snooze', ['minutes' => '{{ reminder.minutes }}'])->save();

    $ok = Flowkit::run('snooze', ['reminder' => ['minutes' => 10]]);
    $bad = Flowkit::run('snooze', ['reminder' => ['minutes' => 'soon']]);

    expect($ok->status)->toBe(RunStatus::Completed)
        ->and($ok->step('s1')->output)->toBe(['minutes' => 10])
        ->and($bad->status)->toBe(RunStatus::Failed)
        ->and($bad->step('s1')->error)->toContain('must be an integer');
});

it('keeps the runner rules strict', function () {
    expect(Number::make('n')->integer()->min(1)->supportsVariables()->getRules())->toBe(['nullable', 'integer', 'min:1']);
});

it('renders as a text input with a numeric keyboard', function () {
    Livewire::test(StepSettings::class, ['stepId' => 's1', 'brick' => 'test.snooze'])
        ->assertSeeHtml('inputmode="numeric"')
        ->assertSeeHtml('inputmode="decimal"')
        ->assertSeeHtml('wire:model="settings.minutes"');
});
