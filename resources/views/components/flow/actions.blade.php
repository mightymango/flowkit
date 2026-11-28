{{-- A flow's actions in the list, in a menu: runs, rename, pause or resume, duplicate, archive or restore. --}}
@props(['flow'])

@php($archived = $flow->status === \MightyMango\Flowkit\Enums\FlowStatus::Archived)

<div x-data="{ open: false }" x-on:keydown.escape="open = false; $refs.toggle.focus()" {{ $attributes->class('relative') }}>
    <button
        type="button"
        x-ref="toggle"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        aria-controls="flowkit-flow-actions-{{ $flow->id }}"
        class="rounded px-2 py-1 text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]"
        aria-label="{{ __('flowkit::editor.list.actions', ['name' => $flow->name]) }}"
    >⋯</button>

    <div
        id="flowkit-flow-actions-{{ $flow->id }}"
        x-show="open"
        x-on:click.outside="open = false"
        x-cloak
        class="absolute right-0 z-10 mt-1 w-44 rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] py-1 text-sm shadow-lg"
    >
        @php($item = 'block w-full px-3 py-1.5 text-left hover:bg-[var(--flowkit-canvas)] focus:bg-[var(--flowkit-canvas)] focus:outline-none')
        <a href="{{ route('flowkit.runs', $flow) }}" class="{{ $item }}">{{ __('flowkit::runs.run_history') }}</a>
        <button type="button" wire:click="edit({{ $flow->id }})" class="{{ $item }}">{{ __('flowkit::editor.list.rename') }}</button>
        @unless ($archived)
            @if ($flow->paused)
                <button type="button" wire:click="resume({{ $flow->id }})" class="{{ $item }}">{{ __('flowkit::editor.list.resume') }}</button>
            @else
                <button type="button" wire:click="pause({{ $flow->id }})" class="{{ $item }}">{{ __('flowkit::editor.list.pause') }}</button>
            @endif
        @endunless
        <button type="button" wire:click="duplicate({{ $flow->id }})" class="{{ $item }}">{{ __('flowkit::editor.list.duplicate') }}</button>
        @if ($archived)
            <button type="button" wire:click="restore({{ $flow->id }})" class="{{ $item }}">{{ __('flowkit::editor.list.restore') }}</button>
        @else
            <button type="button" wire:click="archive({{ $flow->id }})" wire:confirm="{{ __('flowkit::editor.list.confirm_archive') }}" class="{{ $item }} text-[var(--flowkit-danger)]">{{ __('flowkit::editor.list.archive') }}</button>
        @endif
    </div>
</div>
