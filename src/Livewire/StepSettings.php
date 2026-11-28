<?php

namespace MightyMango\Flowkit\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;
use MightyMango\Flowkit\Bricks\Brick;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\TriggerVariables;
use MightyMango\Flowkit\Fields\ModelPicker;
use MightyMango\Flowkit\Fields\SettingsForm;

/**
 * The settings panel for one step (or the trigger): renders the brick's
 * fields, validates them server-side and hands the valid settings to the
 * editor with the `flowkit-step-settings-applied` event. Registered as
 * `flowkit.step-settings`; swap it with `flowkit.components.step-settings`.
 */
class StepSettings extends Component
{
    public const APPLIED = 'flowkit-step-settings-applied';

    #[Locked]
    public string $stepId;

    #[Locked]
    public string $brick;

    /** The flow's trigger node, whose variables fields can offer. */
    #[Locked]
    public ?array $trigger = null;

    public array $settings = [];

    public function mount(string $stepId, string $brick, array $settings = [], ?array $trigger = null): void
    {
        abort_unless(app(BrickRegistry::class)->has($brick), 422, "Unknown brick [{$brick}].");

        $this->stepId = $stepId;
        $this->brick = $brick;
        $this->settings = $settings;
        $this->trigger = $trigger;
    }

    public function apply(): void
    {
        $this->resetValidation();
        $validator = $this->form()->validator($this->settings);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError("settings.{$key}", $message);
                }
            }

            return;
        }

        $this->dispatch(self::APPLIED, stepId: $this->stepId, settings: $validator->validated());
    }

    /**
     * Search the records a model picker offers. Only a ModelPicker field of
     * this step's brick can be searched, on the columns the brick declared.
     *
     * @return list<array{value: int|string, label: string}>
     */
    public function searchRecords(string $field, string $term): array
    {
        $picker = $this->form()->fieldObjects($this->settings)[$field] ?? null;

        abort_unless($picker instanceof ModelPicker, 422);

        $results = [];

        foreach ($picker->search(mb_substr($term, 0, 100)) as $value => $label) {
            $results[] = ['value' => $value, 'label' => $label];
        }

        return $results;
    }

    public function render()
    {
        return view('flowkit::livewire.step-settings', [
            'brickClass' => $this->brickInstance()::class,
            'fields' => $this->form()->fields($this->settings),
        ]);
    }

    private function form(): SettingsForm
    {
        return SettingsForm::for($this->brickInstance(), TriggerVariables::for($this->trigger));
    }

    private function brickInstance(): Brick
    {
        return app(BrickRegistry::class)->resolve($this->brick);
    }
}
