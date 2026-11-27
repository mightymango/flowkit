{{-- The editor's frame: header across the top, canvas left, settings panel right. --}}
@props([])

<div {{ $attributes->class('flex min-h-[70vh] flex-col overflow-hidden rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-canvas)] text-[var(--flowkit-text)]') }}>
    {{ $header ?? '' }}

    <div class="flex flex-1">
        <main class="flex-1 overflow-auto p-8">
            {{ $slot }}
        </main>

        @isset($panel)
            <aside class="w-96 shrink-0 border-l border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] p-6">
                {{ $panel }}
            </aside>
        @endisset
    </div>
</div>
