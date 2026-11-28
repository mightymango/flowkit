{{--
    A "+" that opens a searchable picker of step bricks, grouped, with each
    brick's description. Escape closes it and returns focus to the "+".
--}}
@props([
    'bricks' => [],
    'parentId' => null,
    'branch' => null,
])

@php
    $menuId = 'flowkit-add-'.($parentId ?? 'root').'-'.($branch ?? 'main');
    $texts = collect($bricks)->flatten(1)->map(fn ($brick) => $brick['label'].' '.$brick['description'])->values()->all();
@endphp

<div
    x-data="{
        open: false,
        search: '',
        texts: @js($texts),
        matches(text) { return text.toLowerCase().includes(this.search.trim().toLowerCase()) },
        toggle() {
            this.open = ! this.open
            this.search = ''
            if (this.open) this.$nextTick(() => this.$refs.search.focus())
        },
        close() { this.open = false; this.$refs.trigger.focus() },
    }"
    x-on:keydown.escape="close()"
    {{ $attributes->class('relative') }}
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-bind:aria-expanded="open"
        aria-controls="{{ $menuId }}"
        class="flex size-8 items-center justify-center rounded-full border border-dashed border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] text-[var(--flowkit-muted)] hover:border-[var(--flowkit-accent)] hover:text-[var(--flowkit-accent)]"
        title="{{ __('flowkit::editor.add_step') }}"
        aria-label="{{ __('flowkit::editor.add_step') }}"
    >+</button>

    <div
        id="{{ $menuId }}"
        x-show="open"
        x-on:click.outside="open = false"
        x-cloak
        class="absolute left-1/2 z-10 mt-2 w-72 -translate-x-1/2 rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] p-2 shadow-lg"
    >
        <label for="{{ $menuId }}-search" class="sr-only">{{ __('flowkit::editor.search_steps') }}</label>
        <input
            type="search"
            id="{{ $menuId }}-search"
            x-ref="search"
            x-model="search"
            placeholder="{{ __('flowkit::editor.search_steps') }}"
            class="mb-1 w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-2 py-1.5 text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
        />

        <div class="max-h-80 overflow-y-auto">
            @foreach ($bricks as $group => $items)
                <div x-show="@js(collect($items)->map(fn ($brick) => $brick['label'].' '.$brick['description'])->values()->all()).some(text => matches(text))">
                    <p class="px-2 pt-2 text-xs font-semibold uppercase text-[var(--flowkit-muted)]">{{ $group }}</p>

                    @foreach ($items as $brick)
                        <button
                            type="button"
                            x-show="matches(@js($brick['label'].' '.$brick['description']))"
                            x-on:click="open = false"
                            wire:click="addStep('{{ $brick['key'] }}', {{ $parentId ? "'{$parentId}'" : 'null' }}, {{ $branch ? "'{$branch}'" : 'null' }})"
                            class="block w-full rounded px-2 py-1.5 text-left text-sm text-[var(--flowkit-text)] hover:bg-[var(--flowkit-canvas)] focus:bg-[var(--flowkit-canvas)] focus:outline-none"
                        >
                            <span class="block">{{ $brick['label'] }}</span>
                            @if ($brick['description'])
                                <span class="block text-xs text-[var(--flowkit-muted)]">{{ $brick['description'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endforeach

            <p x-show="! texts.some(text => matches(text))" class="px-2 py-1.5 text-sm text-[var(--flowkit-muted)]">{{ __('flowkit::editor.no_steps_found') }}</p>
        </div>
    </div>
</div>
