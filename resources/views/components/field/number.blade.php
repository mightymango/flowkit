{{--
    A number, or a {{ placeholder }} when the field supports variables, so
    the input is text with a numeric keyboard rather than type="number".
--}}
@props(['field'])

<x-flowkit::field.wrapper :field="$field" {{ $attributes }}>
    <input
        type="text"
        inputmode="{{ ($field['integer'] ?? false) ? 'numeric' : 'decimal' }}"
        id="flowkit-field-{{ $field['name'] }}"
        wire:model{{ ($field['live'] ?? false) ? '.live' : '' }}="settings.{{ $field['name'] }}"
        placeholder="{{ $field['placeholder'] }}"
        class="w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
    />
</x-flowkit::field.wrapper>
