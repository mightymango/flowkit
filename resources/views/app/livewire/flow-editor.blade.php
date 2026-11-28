<div>
    <x-flowkit::editor.layout>
        <x-slot:header>
            <x-flowkit::editor.header :flow="$flow">
                <span
                    x-data="{ shown: false }"
                    x-on:flowkit-saved.window="shown = true; setTimeout(() => shown = false, 2000)"
                    x-show="shown"
                    x-cloak
                    class="text-sm text-[var(--flowkit-muted)]"
                >{{ __('flowkit::editor.saved') }}</span>
                <x-flowkit::button wire:click="save">{{ __('flowkit::editor.save_draft') }}</x-flowkit::button>
                <x-flowkit::button variant="primary" wire:click="publish">{{ __('flowkit::editor.publish') }}</x-flowkit::button>
            </x-flowkit::editor.header>
        </x-slot:header>

        @if ($errorsList)
            <div class="mx-auto mb-6 max-w-xl rounded-md border border-[var(--flowkit-danger)]/30 p-3 text-sm text-[var(--flowkit-danger)]">
                <p class="font-medium">{{ __('flowkit::editor.cannot_publish') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errorsList as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col items-center">
            @if ($trigger)
                <x-flowkit::step.card
                    :step="['id' => 'trigger'] + $trigger"
                    :label="$labels[$trigger['brick']] ?? $trigger['brick']"
                    :summary="$summaries['trigger'] ?? null"
                    :selected="$selected === 'trigger'"
                    kind="trigger"
                />
            @else
                <x-flowkit::empty-state :title="__('flowkit::editor.choose_trigger')" :description="__('flowkit::editor.choose_trigger_description')" class="w-[var(--flowkit-card-width)]">
                    <div class="mt-4 space-y-1">
                        @foreach ($triggers as $group => $items)
                            @foreach ($items as $brick)
                                <x-flowkit::button class="w-full" wire:click="setTrigger('{{ $brick['key'] }}')">{{ $brick['label'] }}</x-flowkit::button>
                            @endforeach
                        @endforeach
                    </div>
                </x-flowkit::empty-state>
            @endif

            @if ($trigger)
                <x-flowkit::step.list :steps="$steps" :labels="$labels" :summaries="$summaries" :selected="$selected" :bricks="$stepBricks" />
            @endif
        </div>

        @if ($selectedNode)
            <x-slot:panel>
                <livewire:flowkit.step-settings
                    :step-id="$selected"
                    :brick="$selectedNode['brick']"
                    :settings="$selectedNode['settings'] ?? []"
                    :key="'settings-'.$selected.'-'.md5(json_encode($selectedNode['settings'] ?? []))"
                />
            </x-slot:panel>
        @endif
    </x-flowkit::editor.layout>
</div>
