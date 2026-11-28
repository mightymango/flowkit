{{-- A branch step: its card, then one column per named branch. --}}
@props([
    'step',
    'labels' => [],
    'summaries' => [],
    'selected' => null,
    'bricks' => [],
])

<div {{ $attributes->class('flex flex-col items-center') }}>
    <x-flowkit::step.card
        :step="$step"
        :label="$labels[$step['brick']] ?? $step['brick']"
        :summary="$summaries[$step['id']] ?? null"
        :selected="$selected === $step['id']"
        kind="branch"
    />

    <div class="flex gap-6">
        @foreach ($step['branches'] as $name => $children)
            <x-flowkit::branch.column
                :name="$name"
                :step-id="$step['id']"
                :steps="$children"
                :labels="$labels"
                :summaries="$summaries"
                :selected="$selected"
                :bricks="$bricks"
            />
        @endforeach
    </div>
</div>
