<?php

namespace MightyMango\Flowkit\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;
use MightyMango\Flowkit\Bricks\Brick;
use MightyMango\Flowkit\Bricks\BrickRegistry;
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

    /**
     * The variables this step can use: the trigger's, and earlier steps'
     * outputs. Fields offer them as options and chips.
     *
     * @var list<array{path: string, label: string, type: string|null}>
     */
    #[Locked]
    public array $variables = [];

    public array $settings = [];

    public function mount(string $stepId, string $brick, array $settings = [], array $variables = []): void
    {
        abort_unless(app(BrickRegistry::class)->has($brick), 422, "Unknown brick [{$brick}].");

        $this->stepId = $stepId;
        $this->brick = $brick;
        $this->settings = $settings;
        $this->variables = $variables;
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
        return SettingsForm::for($this->brickInstance(), $this->variables);
    }

    private function brickInstance(): Brick
    {
        return app(BrickRegistry::class)->resolve($this->brick);
    }
}
