@props([
    'name',
    'stepId',
    'steps' => [],
    'labels' => [],
    'summaries' => [],
    'selected' => null,
    'bricks' => [],
])

<div {{ $attributes->class('flex flex-col items-center') }}>
    <x-flowkit::step.connector />
    <span class="rounded-full border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-0.5 text-xs font-medium text-[var(--flowkit-muted)]">
        {{ \Illuminate\Support\Facades\Lang::has("flowkit::editor.branches.{$name}") ? __("flowkit::editor.branches.{$name}") : \Illuminate\Support\Str::headline($name) }}
    </span>

    <x-flowkit::step.list
        :steps="$steps"
        :labels="$labels"
        :summaries="$summaries"
        :selected="$selected"
        :bricks="$bricks"
        :parent-id="$stepId"
        :branch="$name"
    />
</div>
