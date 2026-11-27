@props(['title', 'description' => null])

<div {{ $attributes->class('rounded-[var(--flowkit-radius)] border border-dashed border-[var(--flowkit-border)] p-10 text-center') }}>
    <p class="font-medium text-[var(--flowkit-text)]">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 text-sm text-[var(--flowkit-muted)]">{{ $description }}</p>
    @endif

    {{ $slot }}
</div>
