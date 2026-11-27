@props([
    'step',
    'label',
    'selected' => false,
    'kind' => 'action',
])

<div
    {{ $attributes->class([
        'group relative w-[var(--flowkit-card-width)] rounded-[var(--flowkit-radius)] border bg-[var(--flowkit-surface)] p-4 text-left text-[var(--flowkit-text)] shadow-sm transition',
        'border-[var(--flowkit-accent)] ring-2 ring-[var(--flowkit-accent)]/20' => $selected,
        'border-[var(--flowkit-border)] hover:border-[var(--flowkit-accent)]/50' => ! $selected,
    ]) }}
>
    <button type="button" wire:click="select('{{ $step['id'] }}')" class="block w-full text-left">
        <x-flowkit::step.badge :kind="$kind" />
        <span class="mt-1 block font-medium">{{ $label }}</span>

        @if (! empty($step['settings']))
            <span class="mt-1 block truncate text-sm text-[var(--flowkit-muted)]">
                {{ collect($step['settings'])->filter(fn ($v) => is_scalar($v))->implode(' · ') }}
            </span>
        @endif
    </button>

    @if ($step['id'] !== 'trigger')
        <div class="absolute right-2 top-2 hidden gap-1 group-hover:flex">
            <button type="button" wire:click="moveStep('{{ $step['id'] }}', -1)" class="rounded px-1 text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]" title="Move up">↑</button>
            <button type="button" wire:click="moveStep('{{ $step['id'] }}', 1)" class="rounded px-1 text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]" title="Move down">↓</button>
            <button type="button" wire:click="removeStep('{{ $step['id'] }}')" wire:confirm="Delete this step?" class="rounded px-1 text-[var(--flowkit-muted)] hover:text-[var(--flowkit-danger)]" title="Delete">✕</button>
        </div>
    @endif
</div>
