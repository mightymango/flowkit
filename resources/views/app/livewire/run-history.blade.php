<div class="space-y-6 text-[var(--flowkit-text)]">
    <header class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('flowkit.edit', $flow) }}" class="text-sm text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]">{{ __('flowkit::runs.back_to_editor') }}</a>
            <h1 class="font-semibold">{{ __('flowkit::runs.title', ['flow' => $flow->name]) }}</h1>
        </div>
        <p class="text-xs text-[var(--flowkit-muted)]">{{ trans_choice('flowkit::runs.retention', config('flowkit.retention_days', 90)) }}</p>
    </header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <section aria-labelledby="flowkit-runs-heading">
            <h2 id="flowkit-runs-heading" class="sr-only">{{ __('flowkit::runs.runs') }}</h2>

            @if ($runs->isEmpty())
                <x-flowkit::empty-state :title="__('flowkit::runs.none')" :description="__('flowkit::runs.none_description')" />
            @else
                <ul class="divide-y divide-[var(--flowkit-border)] rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)]">
                    @foreach ($runs as $item)
                        <li wire:key="run-{{ $item->id }}">
                            <button
                                type="button"
                                wire:click="show({{ $item->id }})"
                                @if ($run === $item->id) aria-current="true" @endif
                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-[var(--flowkit-canvas)] {{ $run === $item->id ? 'bg-[var(--flowkit-canvas)]' : '' }}"
                            >
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">
                                        {{ $item->started_at?->isoFormat('lll') ?? $item->created_at?->isoFormat('lll') }}
                                    </span>
                                    <span class="block text-xs text-[var(--flowkit-muted)]">
                                        {{ __('flowkit::runs.version', ['version' => $item->version->version]) }}
                                        @if ($item->dry_run) · {{ __('flowkit::runs.dry_run') }} @endif
                                        @if ($took = $duration($item)) · {{ $took }} @endif
                                    </span>
                                </span>
                                <x-flowkit::status-pill :status="$item->status" />
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-3">{{ $runs->links() }}</div>
            @endif
        </section>

        <section aria-labelledby="flowkit-run-heading">
            @if ($selectedRun)
                <x-flowkit::run.timeline :run="$selectedRun" :labels="$labels" />
            @else
                <h2 id="flowkit-run-heading" class="sr-only">{{ __('flowkit::runs.detail') }}</h2>
                <p class="text-sm text-[var(--flowkit-muted)]">{{ __('flowkit::runs.choose') }}</p>
            @endif
        </section>
    </div>
</div>
