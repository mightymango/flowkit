<div class="space-y-6 text-[var(--flowkit-text)]">
    <form wire:submit="create" class="flex gap-2">
        <input
            type="text"
            wire:model="name"
            placeholder="Name a new flow"
            class="flex-1 rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm"
        />
        <x-flowkit::button type="submit" variant="primary">Create flow</x-flowkit::button>
    </form>
    @error('name') <p class="text-sm text-[var(--flowkit-danger)]">{{ $message }}</p> @enderror

    @if ($flows->isEmpty())
        <x-flowkit::empty-state title="No flows yet" description="Create one to automate a process." />
    @else
        <ul class="divide-y divide-[var(--flowkit-border)] rounded-[var(--flowkit-radius)] border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)]">
            @foreach ($flows as $flow)
                <li wire:key="flow-{{ $flow->id }}">
                    <a href="{{ route('flowkit.edit', $flow) }}" class="flex items-center justify-between px-4 py-3 hover:bg-[var(--flowkit-canvas)]">
                        <span>
                            <span class="font-medium">{{ $flow->name }}</span>
                            <span class="ml-2 text-xs text-[var(--flowkit-muted)]">{{ $flow->versions_count }} {{ \Illuminate\Support\Str::plural('version', $flow->versions_count) }}</span>
                        </span>
                        <x-flowkit::status-pill :status="$flow->status" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
