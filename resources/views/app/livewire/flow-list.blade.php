<div class="space-y-6 text-[var(--flowkit-text)]">
    <form wire:submit="create" class="flex gap-2">
        <label for="flowkit-new-flow" class="sr-only">{{ __('flowkit::editor.flow_name') }}</label>
        <input
            type="text"
            id="flowkit-new-flow"
            wire:model="name"
            placeholder="{{ __('flowkit::editor.new_flow_placeholder') }}"
            class="flex-1 rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm"
        />
        <x-flowkit::button type="submit" variant="primary">{{ __('flowkit::editor.create_flow') }}</x-flowkit::button>
    </form>
    @error('name') <p class="text-sm text-[var(--flowkit-danger)]">{{ $message }}</p> @enderror

    @if ($flows->isEmpty())
        <x-flowkit::empty-state :title="__('flowkit::editor.no_flows')" :description="__('flowkit::editor.no_flows_description')" />
    @else
        <ul class="divide-y divide-[var(--flowkit-border)] rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)]">
            @foreach ($flows as $flow)
                <li wire:key="flow-{{ $flow->id }}">
                    <a href="{{ route('flowkit.edit', $flow) }}" class="flex items-center justify-between px-4 py-3 hover:bg-[var(--flowkit-canvas)]">
                        <span>
                            <span class="font-medium">{{ $flow->name }}</span>
                            <span class="ml-2 text-xs text-[var(--flowkit-muted)]">{{ trans_choice('flowkit::editor.versions', $flow->versions_count) }}</span>
                        </span>
                        <x-flowkit::status-pill :status="$flow->status" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
