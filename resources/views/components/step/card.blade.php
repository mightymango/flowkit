@props([
    'step',
    'label',
    'summary' => null,
    'problem' => null,
    'selected' => false,
    'kind' => 'action',
])

<div
    {{ $attributes->class([
        'group relative w-[var(--flowkit-card-width)] rounded-[var(--flowkit-radius)] border bg-[var(--flowkit-surface)] p-4 text-left text-[var(--flowkit-text)] shadow-sm transition',
        'border-[var(--flowkit-accent)] ring-2 ring-[var(--flowkit-accent)]/20' => $selected,
        'border-[var(--flowkit-danger)]' => $problem && ! $selected,
        'border-[var(--flowkit-border)] hover:border-[var(--flowkit-accent)]/50' => ! $selected && ! $problem,
    ]) }}
>
    <button type="button" wire:click="select('{{ $step['id'] }}')" class="block w-full text-left">
        <x-flowkit::step.badge :kind="$kind" />
        <span class="mt-1 block font-medium">{{ $label }}</span>

        {{-- The brick's readable summary; without one, the raw values. --}}
        @php($summary ??= collect($step['settings'] ?? [])->filter(fn ($v) => is_scalar($v))->implode(' · '))
        @if ($summary !== '')
            <span class="mt-1 block truncate text-sm text-[var(--flowkit-muted)]">{{ $summary }}</span>
        @endif

        @if ($problem)
            <span class="mt-2 flex items-start gap-1 text-xs text-[var(--flowkit-danger)]">
                <span aria-hidden="true">⚠</span>
                <span><span class="sr-only">{{ __('flowkit::editor.needs_attention') }}: </span>{{ $problem }}</span>
            </span>
        @endif
    </button>

    @if ($step['id'] !== 'trigger')
        <span
            wire:sort:handle
            class="absolute left-1 top-1/2 -translate-y-1/2 cursor-grab select-none px-1 text-[var(--flowkit-muted)] opacity-0 group-hover:opacity-100"
            title="{{ __('flowkit::editor.drag') }}"
            aria-hidden="true"
        >⠿</span>
        <div wire:sort:ignore class="absolute right-2 top-2 hidden gap-1 group-hover:flex group-focus-within:flex">
            <button type="button" wire:click="moveStep('{{ $step['id'] }}', -1)" class="rounded px-1 text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]" title="{{ __('flowkit::editor.move_up') }}" aria-label="{{ __('flowkit::editor.move_up') }}">↑</button>
            <button type="button" wire:click="moveStep('{{ $step['id'] }}', 1)" class="rounded px-1 text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]" title="{{ __('flowkit::editor.move_down') }}" aria-label="{{ __('flowkit::editor.move_down') }}">↓</button>
            <button type="button" wire:click="removeStep('{{ $step['id'] }}')" wire:confirm="{{ __('flowkit::editor.confirm_delete') }}" class="rounded px-1 text-[var(--flowkit-muted)] hover:text-[var(--flowkit-danger)]" title="{{ __('flowkit::editor.delete') }}" aria-label="{{ __('flowkit::editor.delete') }}">✕</button>
        </div>
    @endif
</div>
