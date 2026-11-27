<?php

namespace MightyMango\Flowkit\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use MightyMango\Flowkit\Models\Flow;

class FlowList extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    public function create()
    {
        $this->validate();

        $flow = Flow::create([
            'key' => $this->uniqueKey(Str::slug($this->name)),
            'name' => $this->name,
            'owner_type' => auth()->user() ? auth()->user()::class : null,
            'owner_id' => auth()->id(),
        ]);

        $flow->draft();

        return $this->redirectRoute('flowkit.edit', $flow);
    }

    public function render()
    {
        return view('flowkit::livewire.flow-list', [
            'flows' => Flow::query()
                ->withCount('versions')
                ->latest('updated_at')
                ->get(),
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
