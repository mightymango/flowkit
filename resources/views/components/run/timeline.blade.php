{{-- One run: its summary, then every attempt at every step, in order. --}}
@props([
    'run',
    'labels' => [],
])

<div {{ $attributes->class('space-y-4') }}>
    <div class="flex flex-wrap items-center gap-3">
        <h2 id="flowkit-run-heading" class="font-semibold">{{ __('flowkit::runs.run', ['id' => $run->id]) }}</h2>
        <x-flowkit::status-pill :status="$run->status" />
        @if ($run->dry_run)
            <span class="rounded-full border border-[var(--flowkit-border)] px-2 py-0.5 text-xs text-[var(--flowkit-muted)]">{{ __('flowkit::runs.dry_run') }}</span>
        @endif
    </div>

    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt class="text-[var(--flowkit-muted)]">{{ __('flowkit::runs.version_label') }}</dt>
        <dd>{{ $run->version->version }}</dd>
        <dt class="text-[var(--flowkit-muted)]">{{ __('flowkit::runs.started') }}</dt>
        <dd>{{ $run->started_at?->isoFormat('ll LTS') ?? '—' }}</dd>
        <dt class="text-[var(--flowkit-muted)]">{{ __('flowkit::runs.finished') }}</dt>
        <dd>{{ $run->finished_at?->isoFormat('ll LTS') ?? '—' }}</dd>
    </dl>

    @if ($run->steps->isEmpty())
        <p class="text-sm text-[var(--flowkit-muted)]">{{ __('flowkit::runs.no_steps') }}</p>
    @else
        <ol class="space-y-2">
            @foreach ($run->steps as $step)
                <x-flowkit::run.step-row :step="$step" :label="$labels[$step->brick] ?? $step->brick" wire:key="run-step-{{ $step->id }}" />
            @endforeach
        </ol>
    @endif
</div>
