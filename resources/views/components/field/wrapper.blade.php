{{-- Label, help text and validation error shared by every field. --}}
@props(['field'])

<div {{ $attributes->class('space-y-1') }}>
    <label for="flowkit-field-{{ $field['name'] }}" class="block text-sm font-medium text-[var(--flowkit-text)]">
        {{ $field['label'] }}
        @if ($field['required'])<span class="text-[var(--flowkit-danger)]">*</span>@endif
    </label>

    {{ $slot }}

    @if ($field['help'])
        <p class="text-xs text-[var(--flowkit-muted)]">{{ $field['help'] }}</p>
    @endif

    @if ($field['supportsVariables'])
        <p class="text-xs text-[var(--flowkit-muted)]">Use <code>@{{ variable }}</code> to insert data from earlier steps.</p>
    @endif

    @error('settings.'.$field['name'])
        <p class="text-xs text-[var(--flowkit-danger)]">{{ $message }}</p>
    @enderror
</div>
