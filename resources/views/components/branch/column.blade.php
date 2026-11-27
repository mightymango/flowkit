@props([
    'name',
    'stepId',
    'steps' => [],
    'labels' => [],
    'selected' => null,
    'bricks' => [],
])

<div {{ $attributes->class('flex flex-col items-center') }}>
    <x-flowkit::step.connector />
    <span class="rounded-full border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-0.5 text-xs font-medium text-[var(--flowkit-muted)]">
        {{ \Illuminate\Support\Str::headline($name) }}
    </span>

    <x-flowkit::step.list
        :steps="$steps"
        :labels="$labels"
        :selected="$selected"
        :bricks="$bricks"
        :parent-id="$stepId"
        :branch="$name"
    />
</div>
