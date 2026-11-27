@props(['variant' => 'secondary'])

<button
    {{ $attributes->merge(['type' => 'button'])->class([
        'rounded-md px-3 py-1.5 text-sm font-medium transition disabled:opacity-50',
        'bg-[var(--flowkit-accent)] text-[var(--flowkit-accent-contrast)] hover:opacity-90' => $variant === 'primary',
        'border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] text-[var(--flowkit-text)] hover:bg-[var(--flowkit-canvas)]' => $variant === 'secondary',
    ]) }}
>{{ $slot }}</button>
