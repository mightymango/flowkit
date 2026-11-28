@props(['flow', 'version' => null])

<header {{ $attributes->class('flex items-center justify-between gap-4 border-b border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-6 py-3') }}>
    <div class="flex items-center gap-3">
        <a href="{{ route('flowkit.index') }}" class="text-sm text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]">{{ __('flowkit::editor.back_to_flows') }}</a>
        <h1 class="font-semibold">{{ $flow->name }}</h1>
        <x-flowkit::status-pill :status="$flow->status" />
        @if ($version)
            <span class="text-xs text-[var(--flowkit-muted)]">{{ __('flowkit::editor.version', ['version' => $version]) }}</span>
        @endif
    </div>

    <div class="flex items-center gap-2">
        {{ $slot }}
    </div>
</header>
