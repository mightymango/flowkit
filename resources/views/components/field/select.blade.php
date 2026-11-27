@props(['field'])

<x-flowkit::field.wrapper :field="$field" {{ $attributes }}>
    <select
        id="flowkit-field-{{ $field['name'] }}"
        wire:model="settings.{{ $field['name'] }}"
        class="w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
    >
        <option value="">{{ $field['placeholder'] ?? 'Choose…' }}</option>
        @foreach ($field['options'] ?? [] as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
</x-flowkit::field.wrapper>
