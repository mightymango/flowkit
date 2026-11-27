@props(['field'])

<x-flowkit::field.wrapper :field="$field" {{ $attributes }}>
    <input
        type="text"
        id="flowkit-field-{{ $field['name'] }}"
        wire:model="settings.{{ $field['name'] }}"
        placeholder="{{ $field['placeholder'] }}"
        class="w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
    />
</x-flowkit::field.wrapper>
