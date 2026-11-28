{{-- One attempt at one step. Input and output start collapsed: they can hold personal data. --}}
@props([
    'step',
    'label',
])

<li {{ $attributes->class('rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] p-3') }}>
    <div class="flex items-center justify-between gap-3">
        <span class="min-w-0">
            <span class="block text-sm font-medium">{{ $label }}</span>
            <span class="block text-xs text-[var(--flowkit-muted)]">
                {{ $step->step_id }}
                @if ($step->attempt > 1) · {{ __('flowkit::runs.attempt', ['attempt' => $step->attempt]) }} @endif
                @if ($step->duration_ms !== null) · {{ __('flowkit::runs.milliseconds', ['ms' => number_format($step->duration_ms)]) }} @endif
            </span>
        </span>
        <x-flowkit::status-pill :status="$step->status" />
    </div>

    @if ($step->error)
        <p class="mt-2 text-sm text-[var(--flowkit-danger)]">{{ $step->error }}</p>
    @endif

    @foreach (['input' => __('flowkit::runs.input'), 'output' => __('flowkit::runs.output')] as $key => $heading)
        @if (! empty($step->{$key}))
            <details class="mt-2">
                <summary class="cursor-pointer text-xs font-medium text-[var(--flowkit-muted)]">{{ $heading }}</summary>
                <pre class="mt-1 overflow-x-auto rounded-md bg-[var(--flowkit-canvas)] p-2 text-xs">{{ json_encode($step->{$key}, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        @endif
    @endforeach
</li>
