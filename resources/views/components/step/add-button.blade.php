{{-- A "+" that opens a picker of step bricks, grouped. --}}
@props([
    'bricks' => [],
    'parentId' => null,
    'branch' => null,
])

<div x-data="{ open: false }" {{ $attributes->class('relative') }}>
    <button
        type="button"
        x-on:click="open = ! open"
        class="flex size-8 items-center justify-center rounded-full border border-dashed border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] text-[var(--flowkit-muted)] hover:border-[var(--flowkit-accent)] hover:text-[var(--flowkit-accent)]"
        title="Add a step"
    >+</button>

    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-cloak
        class="absolute left-1/2 z-10 mt-2 w-64 -translate-x-1/2 rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] p-2 shadow-lg"
    >
        @foreach ($bricks as $group => $items)
            <p class="px-2 pt-2 text-xs font-semibold uppercase text-[var(--flowkit-muted)]">{{ $group }}</p>

            @foreach ($items as $brick)
                <button
                    type="button"
                    x-on:click="open = false"
                    wire:click="addStep('{{ $brick['key'] }}', {{ $parentId ? "'{$parentId}'" : 'null' }}, {{ $branch ? "'{$branch}'" : 'null' }})"
                    class="block w-full rounded px-2 py-1.5 text-left text-sm text-[var(--flowkit-text)] hover:bg-[var(--flowkit-canvas)]"
                >{{ $brick['label'] }}</button>
            @endforeach
        @endforeach
    </div>
</div>
