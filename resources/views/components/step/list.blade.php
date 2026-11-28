{{-- Renders a list of steps, recursing into branch groups. --}}
@props([
    'steps' => [],
    'labels' => [],
    'summaries' => [],
    'problems' => [],
    'selected' => null,
    'bricks' => [],
    'parentId' => null,
    'branch' => null,
])

{{--
    Steps can be dragged by their handle within this list, or into any other
    list in the flow (the shared `flowkit-steps` group). The editor's
    handleSort() receives the step id, its new position and this list's id.
--}}
@php($listId = $parentId === null ? 'root' : "{$parentId}:{$branch}")

<div {{ $attributes->class('flex flex-col items-center') }}>
    <div
        wire:sort="handleSort"
        wire:sort:group="flowkit-steps"
        wire:sort:group-id="{{ $listId }}"
        class="flex min-h-4 flex-col items-center"
    >
    @foreach ($steps as $step)
        <div wire:sort:item="{{ $step['id'] }}" wire:key="sortable-{{ $step['id'] }}" class="flex flex-col items-center">
        <x-flowkit::step.connector />

        @if (isset($step['branches']))
            <x-flowkit::branch.group
                :step="$step"
                :labels="$labels"
                :summaries="$summaries"
                :problems="$problems"
                :selected="$selected"
                :bricks="$bricks"
                wire:key="step-{{ $step['id'] }}"
            />
        @else
            <x-flowkit::step.card
                :step="$step"
                :label="$labels[$step['brick']] ?? $step['brick']"
                :summary="$summaries[$step['id']] ?? null"
                :problem="$problems[$step['id']] ?? null"
                :selected="$selected === $step['id']"
                wire:key="step-{{ $step['id'] }}"
            />
        @endif
        </div>
    @endforeach
    </div>

    <x-flowkit::step.connector />
    <x-flowkit::step.add-button :bricks="$bricks" :parent-id="$parentId" :branch="$branch" />
</div>
