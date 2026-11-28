{{-- The editor's frame: header across the top, canvas left, settings panel right. --}}
@props([])

<div {{ $attributes->class('flex min-h-[70vh] flex-col overflow-hidden rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-canvas)] text-[var(--flowkit-text)]') }}>
    {{ $header ?? '' }}

    <div class="flex flex-1">
        <section class="flex-1 overflow-auto p-8" aria-label="{{ __('flowkit::editor.steps') }}">
            {{ $slot }}
        </section>

        @isset($panel)
            <aside aria-label="{{ __('flowkit::editor.step_settings') }}" data-flowkit-settings class="w-96 shrink-0 border-l border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] p-6">
                {{ $panel }}
            </aside>
        @endisset
    </div>
</div>
