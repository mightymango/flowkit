<div
    x-data
    x-on:beforeunload.window="if ($wire.dirty) { $event.preventDefault(); $event.returnValue = '' }"
>
    <x-flowkit::editor.layout>
        <x-slot:header>
            <x-flowkit::editor.header :flow="$flow">
                <x-flowkit::editor.toolbar :flow="$flow" :can-undo="$history !== []" :dirty="$dirty" :can-test="(bool) $trigger" />
            </x-flowkit::editor.header>
        </x-slot:header>

        @if ($testing)
            <x-flowkit::editor.test-panel :recent-runs="$recentRuns" :test-run="$testRun" :labels="$labels" class="mx-auto mb-6 max-w-3xl" />
        @endif

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
                    :problem="$problems['trigger'] ?? null"
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
                <x-flowkit::step.list :steps="$steps" :labels="$labels" :summaries="$summaries" :problems="$problems" :selected="$selected" :bricks="$stepBricks" />
            @endif
        </div>

        @if ($selectedNode)
            <x-slot:panel>
                <livewire:flowkit.step-settings
                    :step-id="$selected"
                    :brick="$selectedNode['brick']"
                    :settings="$selectedNode['settings'] ?? []"
                    :variables="$selectedVariables"
                    :key="'settings-'.$selected.'-'.md5(json_encode([$selectedNode['settings'] ?? [], $selectedVariables]))"
                />

                @if ($destinations)
                    <div class="mt-6 space-y-1 border-t border-[var(--flowkit-border)] pt-4">
                        <label for="flowkit-move-to" class="block text-sm font-medium">{{ __('flowkit::editor.move_to') }}</label>
                        <select
                            id="flowkit-move-to"
                            x-on:change="if ($event.target.value) { $wire.moveTo(@js($selected), $event.target.value); $event.target.value = '' }"
                            aria-describedby="flowkit-move-to-help"
                            class="w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm"
                        >
                            <option value="">{{ __('flowkit::editor.choose') }}</option>
                            @foreach ($destinations as $list => $label)
                                <option value="{{ $list }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p id="flowkit-move-to-help" class="text-xs text-[var(--flowkit-muted)]">{{ __('flowkit::editor.move_to_help') }}</p>
                    </div>
                @endif
            </x-slot:panel>
        @endif
    </x-flowkit::editor.layout>
</div>
