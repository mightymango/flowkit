{{-- Stores an ISO 8601 duration. A friendlier number + unit picker is a phase 2 job. --}}
@props(['field'])

<x-flowkit::field.wrapper :field="$field" {{ $attributes }}>
    <input
        type="text"
        id="flowkit-field-{{ $field['name'] }}"
        wire:model{{ ($field['live'] ?? false) ? '.live' : '' }}="settings.{{ $field['name'] }}"
        placeholder="{{ $field['placeholder'] ?? __('flowkit::editor.duration_placeholder') }}"
        class="w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 font-mono text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
    />
</x-flowkit::field.wrapper>
