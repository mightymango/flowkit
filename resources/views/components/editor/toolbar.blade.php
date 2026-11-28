{{--
    The editor's actions: undo, test, run history, an unsaved-changes
    indicator, save and publish. Leaving with unsaved changes asks first.
--}}
@props([
    'flow',
    'canUndo' => false,
    'dirty' => false,
    'canTest' => false,
])

<div {{ $attributes->class('flex flex-wrap items-center gap-2') }} role="toolbar" aria-label="{{ __('flowkit::editor.toolbar') }}">
    <x-flowkit::button wire:click="undo" :disabled="! $canUndo" title="{{ __('flowkit::editor.undo') }}">↶ {{ __('flowkit::editor.undo') }}</x-flowkit::button>

    @if ($canTest)
        <x-flowkit::button wire:click="openTest">{{ __('flowkit::editor.test.button') }}</x-flowkit::button>
    @endif

    <a href="{{ route('flowkit.runs', $flow) }}" class="px-1 text-sm text-[var(--flowkit-muted)] hover:text-[var(--flowkit-text)]">{{ __('flowkit::runs.run_history') }}</a>

    <span class="mx-1 h-5 w-px bg-[var(--flowkit-border)]" aria-hidden="true"></span>

    <span role="status" class="text-sm text-[var(--flowkit-muted)]">
        @if ($dirty)
            <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-[var(--flowkit-warning)]" aria-hidden="true"></span>{{ __('flowkit::editor.unsaved') }}</span>
        @else
            <span
                x-data="{ shown: false }"
                x-on:flowkit-saved.window="shown = true; setTimeout(() => shown = false, 2000)"
                x-show="shown"
                x-cloak
            >{{ __('flowkit::editor.saved') }}</span>
        @endif
    </span>

    <x-flowkit::button wire:click="save">{{ __('flowkit::editor.save_draft') }}</x-flowkit::button>
    <x-flowkit::button variant="primary" wire:click="publish">{{ __('flowkit::editor.publish') }}</x-flowkit::button>
</div>
