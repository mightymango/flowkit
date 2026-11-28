<form wire:submit="apply" class="space-y-4" aria-label="{{ __('flowkit::editor.step_settings') }}">
    <div>
        <h2 tabindex="-1" class="font-semibold focus:outline-none">{{ __($brickClass::$label) }}</h2>
        @if ($brickClass::$description)
            <p class="mt-1 text-sm text-[var(--flowkit-muted)]">{{ __($brickClass::$description) }}</p>
        @endif
    </div>

    @forelse ($fields as $field)
        <x-dynamic-component :component="$field['component']" :field="$field" wire:key="field-{{ $stepId }}-{{ $field['name'] }}" />
    @empty
        <p class="text-sm text-[var(--flowkit-muted)]">{{ __('flowkit::editor.no_settings') }}</p>
    @endforelse

    @if ($fields)
        <x-flowkit::button type="submit" variant="primary">{{ __('flowkit::editor.apply') }}</x-flowkit::button>
    @endif
</form>
