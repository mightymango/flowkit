<?php

namespace MightyMango\Flowkit\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use MightyMango\Flowkit\Enums\FlowStatus;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Models\FlowRun;

/**
 * Index of flows: owner, last run, status filter and search, and actions to
 * pause, resume, archive, restore, duplicate, rename and describe a flow.
 */
class FlowList extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Url(except: '')]
    public string $search = '';

    /** '', 'draft', 'live', 'paused' or 'archived'. Archived flows only show when asked for. */
    #[Url(except: '')]
    public string $status = '';

    /** The flow whose name and description are being edited. */
    public ?int $editingId = null;

    public string $editName = '';

    public string $editDescription = '';

    public function create()
    {
        $this->validateOnly('name');

        $flow = Flow::create([
            'key' => $this->uniqueKey(Str::slug($this->name)),
            'name' => $this->name,
            'owner_type' => auth()->user() ? auth()->user()::class : null,
            'owner_id' => auth()->id(),
        ]);

        $flow->draft();

        return $this->redirectRoute('flowkit.edit', $flow);
    }

    public function pause(int $id): void
    {
        Flow::findOrFail($id)->update(['paused' => true]);
    }

    public function resume(int $id): void
    {
        Flow::findOrFail($id)->update(['paused' => false]);
    }

    /** Archived flows don't run and are hidden from the list unless filtered for. */
    public function archive(int $id): void
    {
        Flow::findOrFail($id)->update(['status' => FlowStatus::Archived]);
    }

    public function restore(int $id): void
    {
        $flow = Flow::findOrFail($id);
        $flow->update(['status' => $flow->live_version_id ? FlowStatus::Live : FlowStatus::Draft]);
    }

    /** Copy a flow's latest version into a new flow's draft, owned by you. */
    public function duplicate(int $id)
    {
        $source = Flow::findOrFail($id);
        $name = __('flowkit::editor.list.copy_of', ['name' => $source->name]);

        $copy = Flow::create([
            'key' => $this->uniqueKey(Str::slug($name)),
            'name' => Str::limit($name, 255, ''),
            'description' => $source->description,
            'owner_type' => auth()->user() ? auth()->user()::class : null,
            'owner_id' => auth()->id(),
        ]);

        $copy->versions()->create([
            'version' => 1,
            'definition' => $source->latestVersion()->first()?->definition,
        ]);

        return $this->redirectRoute('flowkit.edit', $copy);
    }

    public function edit(int $id): void
    {
        $flow = Flow::findOrFail($id);

        $this->editingId = $flow->id;
        $this->editName = $flow->name;
        $this->editDescription = (string) $flow->description;
        $this->resetValidation();
    }

    public function saveDetails(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editDescription' => 'nullable|string|max:2000',
        ], attributes: [
            'editName' => __('flowkit::editor.flow_name'),
            'editDescription' => __('flowkit::editor.list.description'),
        ]);

        Flow::findOrFail($this->editingId)->update([
            'name' => $this->editName,
            'description' => $this->editDescription === '' ? null : $this->editDescription,
        ]);

        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editName', 'editDescription');
        $this->resetValidation();
    }

    public function render()
    {
        $flows = Flow::query()
            ->with('owner')
            ->withCount('versions')
            ->addSelect(['last_run_id' => FlowRun::query()
                ->select('flow_runs.id')
                ->join('flow_versions', 'flow_versions.id', '=', 'flow_runs.flow_version_id')
                ->whereColumn('flow_versions.flow_id', 'flows.id')
                ->where('flow_runs.dry_run', false)
                ->latest('flow_runs.id')
                ->limit(1),
            ])
            ->when(trim($this->search) !== '', fn ($query) => $query->whereRaw(
                'lower(name) like ? escape ?',
                ['%'.addcslashes(mb_strtolower(trim($this->search)), '\\%_').'%', '\\'],
            ))
            ->when($this->status === 'paused', fn ($query) => $query->where('paused', true)->where('status', '!=', FlowStatus::Archived))
            ->when(in_array($this->status, ['draft', 'live', 'archived'], true), fn ($query) => $query->where('status', $this->status))
            ->when($this->status === '', fn ($query) => $query->where('status', '!=', FlowStatus::Archived))
            ->latest('updated_at')
            ->get();

        return view('flowkit::livewire.flow-list', [
            'flows' => $flows,
            'lastRuns' => FlowRun::query()->whereIn('id', $flows->pluck('last_run_id')->filter())->get()->keyBy('id'),
        ]);
    }

    private function uniqueKey(string $base): string
    {
        $key = $base ?: 'flow';
        $i = 2;

        while (Flow::where('key', $key)->exists()) {
            $key = "{$base}-".$i++;
        }

        return $key;
    }
}
