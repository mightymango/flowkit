{{--
    Buttons that insert a {{ variable }} placeholder into a field at the
    cursor, replacing any selected text. `for` is the id of the field's
    input or textarea; `variables` lists what this step can use.
--}}
@props([
    'for',
    'variables' => [],
])

<div {{ $attributes->class('flex flex-wrap items-center gap-1') }} role="group" aria-labelledby="{{ $for }}-chips-label">
    <span id="{{ $for }}-chips-label" class="text-xs text-[var(--flowkit-muted)]">{{ __('flowkit::editor.insert_variable') }}:</span>
    @foreach ($variables as $variable)
        <button
            type="button"
            title="{{ $variable['path'] }}"
            x-data
            x-on:mousedown.prevent
            {{-- Semicolons matter: @js() swallows the line break after it. --}}
            x-on:click="
                let field = document.getElementById(@js($for));
                let text = @js(\MightyMango\Flowkit\Engine\Expression::placeholder($variable['path']));
                let start = field.selectionStart ?? field.value.length;
                let end = field.selectionEnd ?? start;
                field.value = field.value.slice(0, start) + text + field.value.slice(end);
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.focus();
                field.setSelectionRange(start + text.length, start + text.length);
            "
            class="rounded-full border border-[var(--flowkit-border)] bg-[var(--flowkit-canvas)] px-2 py-0.5 text-xs text-[var(--flowkit-text)] hover:border-[var(--flowkit-accent)] focus:border-[var(--flowkit-accent)] focus:outline-none"
        >{{ $variable['label'] }}</button>
    @endforeach
</div>
