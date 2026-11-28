<?php

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Livewire\StepSettings;
use MightyMango\Flowkit\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * The settings panel the editor shows for a step (the selected one by
 * default), mounted the way the editor mounts it.
 */
function settingsPanel(Testable $editor, ?string $stepId = null): Testable
{
    $stepId ??= $editor->get('selected');
    $node = $stepId === 'trigger'
        ? $editor->get('trigger')
        : (new Definition($editor->get('trigger'), $editor->get('steps')))->step($stepId);

    return Livewire::test(StepSettings::class, [
        'stepId' => $stepId,
        'brick' => $node['brick'],
        'settings' => $node['settings'] ?? [],
    ]);
}

/**
 * Fill in and apply a step's settings panel, then deliver its event to the
 * editor, as the browser would. Returns the panel, to assert on its errors.
 */
function applySettings(Testable $editor, array $settings, ?string $stepId = null): Testable
{
    $panel = settingsPanel($editor, $stepId);

    foreach ($settings as $key => $value) {
        $panel->set("settings.{$key}", $value);
    }

    $panel->call('apply');

    foreach (data_get($panel->effects, 'dispatches', []) as $dispatch) {
        if ($dispatch['name'] === StepSettings::APPLIED) {
            $editor->dispatch(StepSettings::APPLIED, ...$dispatch['params']);
        }
    }

    return $panel;
}
