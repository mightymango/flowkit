{{--
    Test the draft as a dry run: trigger data as JSON (pre-filled from the
    trigger's variables, or from a recent run), then the result.
--}}
@props([
    'recentRuns' => [],
    'testRun' => null,
    'labels' => [],
])

<section {{ $attributes->class('space-y-4 rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] p-4') }} aria-labelledby="flowkit-test-heading">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 id="flowkit-test-heading" class="font-semibold">{{ __('flowkit::editor.test.title') }}</h2>
            <p class="mt-1 text-sm text-[var(--flowkit-muted)]">{{ __('flowkit::editor.test.description') }}</p>
        </div>
        <button type="button" wire:click="closeTest" class="text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]" aria-label="{{ __('flowkit::editor.test.close') }}">✕</button>
    </div>

    <form wire:submit="runTest" class="space-y-3">
        <div class="space-y-1">
            <label for="flowkit-test-payload" class="block text-sm font-medium">{{ __('flowkit::editor.test.payload') }}</label>
            <textarea
                id="flowkit-test-payload"
                wire:model="testPayload"
                rows="8"
                spellcheck="false"
                aria-describedby="flowkit-test-payload-help"
                class="w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 font-mono text-xs text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
            ></textarea>
            <p id="flowkit-test-payload-help" class="text-xs text-[var(--flowkit-muted)]">{{ __('flowkit::editor.test.payload_help') }}</p>
            @error('testPayload')
                <p class="text-xs text-[var(--flowkit-danger)]">{{ $message }}</p>
            @enderror
        </div>

        @if (count($recentRuns))
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-[var(--flowkit-muted)]">{{ __('flowkit::editor.test.use_recent') }}</span>
                @foreach ($recentRuns as $recent)
                    <button type="button" wire:click="useRun({{ $recent->id }})" wire:key="recent-{{ $recent->id }}" class="rounded-full border border-[var(--flowkit-border)] px-2 py-0.5 text-xs hover:border-[var(--flowkit-accent)]">
                        {{ ($recent->started_at ?? $recent->created_at)?->isoFormat('lll') }}
                    </button>
                @endforeach
            </div>
        @endif

        <x-flowkit::button type="submit" variant="primary">{{ __('flowkit::editor.test.run') }}</x-flowkit::button>
    </form>

    @if ($testRun)
        <div class="border-t border-[var(--flowkit-border)] pt-4" role="status">
            <x-flowkit::run.timeline :run="$testRun" :labels="$labels" />
        </div>
    @endif
</section>
