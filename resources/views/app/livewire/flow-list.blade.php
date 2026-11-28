<div class="space-y-6 text-[var(--flowkit-text)]">
    <h1 class="text-lg font-semibold">{{ __('flowkit::editor.flows') }}</h1>

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

    <div class="flex flex-wrap gap-2" role="search">
        <label for="flowkit-search-flows" class="sr-only">{{ __('flowkit::editor.list.search') }}</label>
        <input
            type="search"
            id="flowkit-search-flows"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ __('flowkit::editor.list.search') }}"
            class="min-w-48 flex-1 rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm"
        />
        <label for="flowkit-filter-status" class="sr-only">{{ __('flowkit::editor.list.status') }}</label>
        <select id="flowkit-filter-status" wire:model.live="status" class="rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm">
            <option value="">{{ __('flowkit::editor.list.all') }}</option>
            <option value="draft">{{ __('flowkit::status.draft') }}</option>
            <option value="live">{{ __('flowkit::status.live') }}</option>
            <option value="paused">{{ __('flowkit::editor.list.paused') }}</option>
            <option value="archived">{{ __('flowkit::status.archived') }}</option>
        </select>
    </div>

    @if ($flows->isEmpty())
        @if ($search !== '' || $status !== '')
            <p class="text-sm text-[var(--flowkit-muted)]">{{ __('flowkit::editor.list.no_matches') }}</p>
        @else
            <x-flowkit::empty-state :title="__('flowkit::editor.no_flows')" :description="__('flowkit::editor.no_flows_description')" />
        @endif
    @else
        <ul class="divide-y divide-[var(--flowkit-border)] rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)]">
            @foreach ($flows as $flow)
                @php($lastRun = $lastRuns[$flow->last_run_id] ?? null)
                <li wire:key="flow-{{ $flow->id }}" class="px-4 py-3">
                    @if ($editingId === $flow->id)
                        <form wire:submit="saveDetails" class="space-y-2">
                            <div>
                                <label for="flowkit-edit-name" class="block text-sm font-medium">{{ __('flowkit::editor.flow_name') }}</label>
                                <input type="text" id="flowkit-edit-name" wire:model="editName" class="mt-1 w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm" />
                                @error('editName') <p class="text-xs text-[var(--flowkit-danger)]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="flowkit-edit-description" class="block text-sm font-medium">{{ __('flowkit::editor.list.description') }}</label>
                                <textarea id="flowkit-edit-description" wire:model="editDescription" rows="2" class="mt-1 w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm"></textarea>
                                @error('editDescription') <p class="text-xs text-[var(--flowkit-danger)]">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex gap-2">
                                <x-flowkit::button type="submit" variant="primary">{{ __('flowkit::editor.list.save') }}</x-flowkit::button>
                                <x-flowkit::button wire:click="cancelEdit">{{ __('flowkit::editor.list.cancel') }}</x-flowkit::button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ route('flowkit.edit', $flow) }}" class="min-w-0 flex-1">
                                <span class="font-medium">{{ $flow->name }}</span>
                                <span class="ml-2 text-xs text-[var(--flowkit-muted)]">{{ trans_choice('flowkit::editor.versions', $flow->versions_count) }}</span>
                                @if ($flow->description)
                                    <span class="block truncate text-sm text-[var(--flowkit-muted)]">{{ $flow->description }}</span>
                                @endif
                                <span class="block text-xs text-[var(--flowkit-muted)]">
                                    {{ __('flowkit::editor.list.owner', ['owner' => $flow->ownerName() ?? __('flowkit::editor.list.nobody')]) }}
                                    ·
                                    @if ($lastRun)
                                        {{ __('flowkit::editor.list.last_run', ['when' => ($lastRun->started_at ?? $lastRun->created_at)->diffForHumans(), 'status' => $lastRun->status->label()]) }}
                                    @else
                                        {{ __('flowkit::editor.list.never_run') }}
                                    @endif
                                </span>
                            </a>

                            @if ($flow->paused && $flow->status !== \MightyMango\Flowkit\Enums\FlowStatus::Archived)
                                <span class="rounded-full border border-[var(--flowkit-warning)]/30 px-2 py-0.5 text-xs font-medium text-[var(--flowkit-warning)]">{{ __('flowkit::editor.list.paused') }}</span>
                            @endif
                            <x-flowkit::status-pill :status="$flow->status" />

                            <x-flowkit::flow.actions :flow="$flow" />
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
