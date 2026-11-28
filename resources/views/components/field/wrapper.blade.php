{{-- Label, help text and validation error shared by every field. --}}
@props(['field'])

<div {{ $attributes->class('space-y-1') }}>
    <label for="flowkit-field-{{ $field['name'] }}" class="block text-sm font-medium text-[var(--flowkit-text)]">
        {{ $field['label'] }}
        @if ($field['required'])<span class="text-[var(--flowkit-danger)]" aria-hidden="true">*</span><span class="sr-only">({{ __('flowkit::editor.required') }})</span>@endif
    </label>

    {{ $slot }}

    @if ($field['help'])
        <p class="text-xs text-[var(--flowkit-muted)]">{{ $field['help'] }}</p>
    @endif

    @if ($field['supportsVariables'])
        <p class="text-xs text-[var(--flowkit-muted)]">{!! __('flowkit::editor.variables_hint', ['placeholder' => '<code>&#123;&#123; variable &#125;&#125;</code>']) !!}</p>
    @endif

    @error('settings.'.$field['name'])
        <p class="text-xs text-[var(--flowkit-danger)]">{{ $message }}</p>
    @enderror
</div>
