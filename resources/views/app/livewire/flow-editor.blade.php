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
                >Saved</span>
                <x-flowkit::button wire:click="save">Save draft</x-flowkit::button>
                <x-flowkit::button variant="primary" wire:click="publish">Publish</x-flowkit::button>
            </x-flowkit::editor.header>
        </x-slot:header>

        @if ($errorsList)
            <div class="mx-auto mb-6 max-w-xl rounded-md border border-[var(--flowkit-danger)]/30 p-3 text-sm text-[var(--flowkit-danger)]">
                <p class="font-medium">This flow can't be published yet:</p>
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
                    :selected="$selected === 'trigger'"
                    kind="trigger"
                />
            @else
                <x-flowkit::empty-state title="Choose a trigger" description="What starts this flow?" class="w-[var(--flowkit-card-width)]">
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
                <x-flowkit::step.list :steps="$steps" :labels="$labels" :selected="$selected" :bricks="$stepBricks" />
            @endif
        </div>

        @if ($selectedBrick)
            <x-slot:panel>
                <form wire:submit="applySettings" class="space-y-4">
                    <div>
                        <h2 class="font-semibold">{{ $selectedBrick::$label }}</h2>
                        @if ($selectedBrick::$description)
                            <p class="mt-1 text-sm text-[var(--flowkit-muted)]">{{ $selectedBrick::$description }}</p>
                        @endif
                    </div>

                    @forelse ($fields as $field)
                        <x-dynamic-component :component="$field['component']" :field="$field" wire:key="field-{{ $selected }}-{{ $field['name'] }}" />
                    @empty
                        <p class="text-sm text-[var(--flowkit-muted)]">This step has no settings.</p>
                    @endforelse

                    @if ($fields)
                        <x-flowkit::button type="submit" variant="primary">Apply</x-flowkit::button>
                    @endif
                </form>
            </x-slot:panel>
        @endif
    </x-flowkit::editor.layout>
</div>
