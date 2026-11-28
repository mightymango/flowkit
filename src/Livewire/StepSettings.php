<?php

namespace MightyMango\Flowkit\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;
use MightyMango\Flowkit\Bricks\Brick;
use MightyMango\Flowkit\Bricks\BrickRegistry;
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

    public array $settings = [];

    public function mount(string $stepId, string $brick, array $settings = []): void
    {
        abort_unless(app(BrickRegistry::class)->has($brick), 422, "Unknown brick [{$brick}].");

        $this->stepId = $stepId;
        $this->brick = $brick;
        $this->settings = $settings;
    }

    public function apply(): void
    {
        $this->resetValidation();
        $validator = SettingsForm::for($this->brickInstance())->validator($this->settings);

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

    public function render()
    {
        $brick = $this->brickInstance();

        return view('flowkit::livewire.step-settings', [
            'brickClass' => $brick::class,
            'fields' => SettingsForm::for($brick)->fields($this->settings),
        ]);
    }

    private function brickInstance(): Brick
    {
        return app(BrickRegistry::class)->resolve($this->brick);
    }
}
