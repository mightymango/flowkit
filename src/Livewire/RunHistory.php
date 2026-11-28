<?php

namespace MightyMango\Flowkit\Livewire;

use Carbon\CarbonInterface;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Models\Flow;
use MightyMango\Flowkit\Models\FlowRun;

/**
 * A flow's runs, newest first, and one run's steps in detail: each attempt's
 * input, output, error, duration and attempt number. Run data can hold
 * personal data, so inputs and outputs start collapsed. Nothing older than
 * `flowkit.retention_days` exists to show: old runs are pruned.
 */
class RunHistory extends Component
{
    use WithPagination;

    #[Locked]
    public int $flowId;

    /** The run shown in detail, kept in the URL so it can be shared. */
    #[Url]
    public ?int $run = null;

    public function mount(Flow $flow): void
    {
        abort_unless(Flow::inEditor()->whereKey($flow->getKey())->exists(), 404);

        $this->flowId = $flow->id;
    }

    public function show(?int $run): void
    {
        $this->run = $run;
    }

    public function render()
    {
        $flow = Flow::inEditor()->findOrFail($this->flowId);

        // Scoped through the flow, so a run id from another flow shows nothing.
        $selected = $this->run
            ? $flow->runs()->with(['version', 'steps' => fn ($query) => $query->reorder()->orderBy('id')])->find($this->run)
            : null;

        return view('flowkit::livewire.run-history', [
            'flow' => $flow,
            'runs' => $flow->runs()->with('version')->latest('flow_runs.id')->paginate(25),
            'selectedRun' => $selected,
            'duration' => self::duration(...),
            'labels' => array_map(fn ($class) => __($class::$label), app(BrickRegistry::class)->all()),
        ]);
    }

    /** How long a run or step took, for display. */
    public static function duration(?FlowRun $run): ?string
    {
        if (! $run?->started_at || ! $run->finished_at) {
            return null;
        }

        return $run->started_at->diffForHumans($run->finished_at, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]);
    }
}
