{{-- Renders a list of steps, recursing into branch groups. --}}
@props([
    'steps' => [],
    'labels' => [],
    'selected' => null,
    'bricks' => [],
    'parentId' => null,
    'branch' => null,
])

<div {{ $attributes->class('flex flex-col items-center') }}>
    @foreach ($steps as $step)
        <x-flowkit::step.connector />

        @if (isset($step['branches']))
            <x-flowkit::branch.group
                :step="$step"
                :labels="$labels"
                :selected="$selected"
                :bricks="$bricks"
                wire:key="step-{{ $step['id'] }}"
            />
        @else
            <x-flowkit::step.card
                :step="$step"
                :label="$labels[$step['brick']] ?? $step['brick']"
                :selected="$selected === $step['id']"
                wire:key="step-{{ $step['id'] }}"
            />
        @endif
    @endforeach

    <x-flowkit::step.connector />
    <x-flowkit::step.add-button :bricks="$bricks" :parent-id="$parentId" :branch="$branch" />
</div>
