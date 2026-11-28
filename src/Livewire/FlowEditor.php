<?php

namespace MightyMango\Flowkit\Livewire;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Core\Branch;
use MightyMango\Flowkit\Bricks\StepVariables;
use MightyMango\Flowkit\Bricks\TriggerVariables;
use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Exceptions\InvalidDefinition;
use MightyMango\Flowkit\Facades\Flowkit;
use MightyMango\Flowkit\Fields\SettingsForm;
use MightyMango\Flowkit\Models\Flow;

/**
 * Loads a flow's draft version, holds the step tree, and adds, moves and
 * deletes steps. The selected step's settings are edited in the
 * `flowkit.step-settings` component, which sends valid settings back with
 * the `flowkit-step-settings-applied` event. Logic lives here; markup lives
 * in the publishable flowkit:: Blade components.
 */
class FlowEditor extends Component
{
    #[Locked]
    public int $flowId;

    /**
     * The tree being edited. Locked: it only changes through this
     * component's actions, which validate what they store.
     */
    #[Locked]
    public ?array $trigger = null;

    #[Locked]
    public array $steps = [];

    /** Earlier trees for undo, newest last, at most HISTORY_LIMIT. */
    #[Locked]
    public array $history = [];

    /** Whether there are changes since the draft was last saved. */
    #[Locked]
    public bool $dirty = false;

    public const HISTORY_LIMIT = 20;

    public ?string $selected = null;

    public array $errorsList = [];

    /** Whether the test panel is open. */
    public bool $testing = false;

    /** The test run's trigger data, as JSON. */
    public string $testPayload = '';

    #[Locked]
    public ?int $testRunId = null;

    public function mount(Flow $flow): void
    {
        $this->flowId = $flow->id;

        $definition = $flow->draft()->toDefinition();
        $this->trigger = $definition->trigger;
        $this->steps = $definition->steps;
    }

    public function setTrigger(string $brick): void
    {
        $this->assertBrick($brick, 'trigger');
        $this->remember();
        $this->trigger = ['brick' => $brick, 'settings' => []];
        $this->select('trigger');
    }

    /**
     * Add a step at the end of a list: the top level, or a named branch of
     * a branch step.
     */
    public function addStep(string $brick, ?string $parentId = null, ?string $branch = null): void
    {
        $this->assertBrick($brick);

        $step = ['id' => 's'.Str::lower(Str::random(6)), 'brick' => $brick, 'settings' => []];

        $instance = app(BrickRegistry::class)->resolve($brick);
        if ($instance instanceof Branch) {
            $step['branches'] = array_fill_keys($instance->branches(), []);
        }

        $this->remember();

        if ($parentId === null) {
            $this->steps[] = $step;
        } else {
            $this->steps = $this->mapTree($this->steps, function (array $node) use ($parentId, $branch, $step) {
                if ($node['id'] === $parentId && isset($node['branches'][$branch])) {
                    $node['branches'][$branch][] = $step;
                }

                return $node;
            });
        }

        $this->select($step['id']);
    }

    public function removeStep(string $id): void
    {
        $this->remember();
        $this->steps = $this->filterTree($this->steps, fn (array $node) => $node['id'] !== $id);

        if ($this->selected === $id) {
            $this->selected = null;
        }
    }

    public function moveStep(string $id, int $offset): void
    {
        $move = function (array $list) use (&$move, $id, $offset): array {
            foreach ($list as $i => $node) {
                if ($node['id'] === $id) {
                    $j = max(0, min(count($list) - 1, $i + $offset));
                    [$list[$i], $list[$j]] = [$list[$j], $list[$i]];

                    return $list;
                }

                foreach ($node['branches'] ?? [] as $name => $children) {
                    $list[$i]['branches'][$name] = $move($children);
                }
            }

            return $list;
        };

        $this->remember();
        $this->steps = $move($this->steps);
    }

    /**
     * wire:sort's handler: a step was dropped at a position in a list,
     * either the main flow (`root`) or a branch (`<branch step id>:<name>`).
     */
    public function handleSort(string $id, int $position, string $list = 'root'): void
    {
        $this->moveTo($id, $list, $position);
    }

    /**
     * Move a step, with any steps inside it, to a position in a list (the
     * end by default). The keyboard alternative to dragging. A step can't
     * move into one of its own branches.
     */
    public function moveTo(string $id, string $list, ?int $position = null): void
    {
        $node = $this->findStep($id);

        if (! $node || $list === '' || ! array_key_exists($list, $this->destinations())) {
            return;
        }

        [$parentId, $branch] = $list === 'root' ? [null, null] : explode(':', $list, 2);

        if ($parentId !== null && ($parentId === $id || $this->containsStep($node, $parentId))) {
            return;
        }

        $this->remember();
        $steps = $this->filterTree($this->steps, fn (array $step) => $step['id'] !== $id);

        $insert = function (array $list) use ($node, $position): array {
            $at = $position === null ? count($list) : max(0, min(count($list), $position));
            array_splice($list, $at, 0, [$node]);

            return $list;
        };

        $this->steps = $parentId === null
            ? $insert($steps)
            : $this->mapTree($steps, function (array $step) use ($parentId, $branch, $insert) {
                if ($step['id'] === $parentId && isset($step['branches'][$branch])) {
                    $step['branches'][$branch] = $insert($step['branches'][$branch]);
                }

                return $step;
            });
    }

    /** Select a step and move keyboard focus to its settings. */
    public function select(?string $id): void
    {
        $this->selected = $id;

        if ($id !== null) {
            $this->dispatch('flowkit-focus-settings');
        }
    }

    /**
     * Store settings from the step settings panel. Event parameters come
     * from the browser, so they're validated again here against the brick
     * of the step in this editor's own tree, never the brick the event names.
     */
    #[On(StepSettings::APPLIED)]
    public function storeSettings(string $stepId, array $settings): void
    {
        $node = $stepId === 'trigger' ? $this->trigger : $this->findStep($stepId);

        if (! $node) {
            return;
        }

        $brick = app(BrickRegistry::class)->resolve($node['brick']);
        $variables = StepVariables::before($this->trigger, $this->steps, $stepId);
        $validator = SettingsForm::for($brick, $variables)->validator($settings);

        if ($validator->fails()) {
            return;
        }

        $validated = $validator->validated();
        $this->remember();

        if ($stepId === 'trigger') {
            $this->trigger['settings'] = $validated;

            return;
        }

        $this->steps = $this->mapTree($this->steps, function (array $step) use ($stepId, $validated) {
            if ($step['id'] === $stepId) {
                $step['settings'] = $validated;
            }

            return $step;
        });
    }

    public function save(): void
    {
        $flow = Flow::findOrFail($this->flowId);
        $definition = new Definition($this->trigger, $this->steps);

        $flow->draft()->update(['definition' => $definition->toArray()]);
        $flow->touch();

        $this->dirty = false;
        $this->errorsList = [];
        $this->dispatch('flowkit-saved');
    }

    public function publish(): void
    {
        $this->save();

        if ($problems = $this->problems()) {
            $labels = $this->labels();
            $this->errorsList = [];

            foreach ($problems as $id => $message) {
                $node = $id === 'trigger' ? $this->trigger : $this->findStep($id);
                $this->errorsList[] = ($labels[$node['brick']] ?? $node['brick']).': '.$message;
            }

            return;
        }

        $flow = Flow::findOrFail($this->flowId);

        try {
            $flow->publish($flow->draft(), auth()->user());
        } catch (InvalidDefinition $e) {
            $this->errorsList = $e->errors;

            return;
        }

        $this->dispatch('flowkit-published');
    }

    /**
     * Open the test panel, with trigger data shaped like the trigger's
     * variables to fill in.
     */
    public function openTest(): void
    {
        $sample = [];

        foreach (TriggerVariables::for($this->trigger) as $variable) {
            data_set($sample, $variable['path'], '');
        }

        $this->testPayload = json_encode((object) $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->testRunId = null;
        $this->testing = true;
        $this->resetValidation('testPayload');
    }

    /** Fill the test panel with the trigger data of one of this flow's runs. */
    public function useRun(int $runId): void
    {
        $run = Flow::findOrFail($this->flowId)->runs()->findOrFail($runId);

        $this->testPayload = json_encode((object) ($run->payload ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Save the draft and test it as a dry run with the given trigger data:
     * unsafe actions only say what they would have done. The result shows
     * below, and in the run history as a test run.
     */
    public function runTest(): void
    {
        $this->resetValidation('testPayload');
        $payload = json_decode($this->testPayload, true);

        if (! is_array($payload) || array_is_list($payload) && $payload !== []) {
            $this->addError('testPayload', __('flowkit::editor.test.invalid_json'));

            return;
        }

        $this->save();
        $flow = Flow::findOrFail($this->flowId);
        $draft = $flow->draft();

        try {
            $draft->toDefinition()->validate();
        } catch (InvalidDefinition $e) {
            $this->errorsList = $e->errors;

            return;
        }

        $this->testRunId = Flowkit::testVersion($draft, $payload)->id;
    }

    /** Go back to the tree before the last change. */
    public function undo(): void
    {
        $previous = array_pop($this->history);

        if ($previous === null) {
            return;
        }

        [$this->trigger, $this->steps] = $previous;
        $this->dirty = true;

        if ($this->selected && $this->selected !== 'trigger' && ! $this->findStep($this->selected)) {
            $this->selected = null;
        }
    }

    public function closeTest(): void
    {
        $this->testing = false;
        $this->testRunId = null;
    }

    public function render()
    {
        $registry = app(BrickRegistry::class);
        $node = $this->selected === 'trigger' ? $this->trigger : $this->findStep($this->selected);

        return view('flowkit::livewire.flow-editor', [
            'flow' => $flow = Flow::findOrFail($this->flowId),
            'version' => $flow->latestVersion()->value('version'),
            'triggers' => $registry->grouped('trigger'),
            'stepBricks' => array_merge_recursive($registry->grouped('action'), $registry->grouped('branch'), $registry->grouped('condition')),
            'labels' => $this->labels(),
            'problems' => $this->problems(),
            'summaries' => $this->summaries(),
            'selectedNode' => $node && $registry->has($node['brick']) ? $node : null,
            'testRun' => $this->testRunId
                ? Flow::findOrFail($this->flowId)->runs()->with(['version', 'steps' => fn ($query) => $query->reorder()->orderBy('id')])->find($this->testRunId)
                : null,
            'recentRuns' => $this->testing
                ? Flow::findOrFail($this->flowId)->runs()->where('dry_run', false)->latest('flow_runs.id')->limit(5)->get(['flow_runs.id', 'flow_runs.started_at', 'flow_runs.created_at'])
                : collect(),
            'destinations' => $this->selected && $this->selected !== 'trigger' ? $this->destinationsFor($this->selected) : [],
            'selectedVariables' => $this->selected ? StepVariables::before($this->trigger, $this->steps, $this->selected) : [],
        ]);
    }

    /**
     * Each step's card summary, keyed by step id (the trigger's under
     * `trigger`).
     *
     * @return array<string, string>
     */
    private function summaries(): array
    {
        $registry = app(BrickRegistry::class);
        $summaries = [];

        $collect = function (array $nodes) use (&$collect, &$summaries, $registry): void {
            foreach ($nodes as $id => $node) {
                $stepId = $node['id'] ?? $id;

                if ($registry->has($node['brick'] ?? '')) {
                    $summaries[$stepId] = $registry->resolve($node['brick'])
                        ->summary($node['settings'] ?? [], StepVariables::before($this->trigger, $this->steps, $stepId));
                }

                foreach ($node['branches'] ?? [] as $children) {
                    $collect($children);
                }
            }
        };

        $collect($this->trigger ? ['trigger' => $this->trigger] : []);
        $collect($this->steps);

        return $summaries;
    }

    /**
     * Every list a step can be moved to, keyed as for moveTo(), with a label.
     *
     * @return array<string, string>
     */
    private function destinations(): array
    {
        $labels = $this->labels();
        $destinations = ['root' => (string) __('flowkit::editor.main_flow')];

        $collect = function (array $steps) use (&$collect, &$destinations, $labels): void {
            foreach ($steps as $step) {
                foreach ($step['branches'] ?? [] as $name => $children) {
                    $branch = Lang::has("flowkit::editor.branches.{$name}") ? __("flowkit::editor.branches.{$name}") : Str::headline($name);
                    $destinations["{$step['id']}:{$name}"] = ($labels[$step['brick']] ?? $step['brick'])." ({$step['id']}) → {$branch}";
                    $collect($children);
                }
            }
        };

        $collect($this->steps);

        return $destinations;
    }

    /**
     * Where the selected step can move: every list except its own branches.
     *
     * @return array<string, string>
     */
    private function destinationsFor(string $id): array
    {
        $node = $this->findStep($id);

        return array_filter(
            $this->destinations(),
            fn (string $list) => $list === 'root' || ($node && ! in_array(explode(':', $list)[0], [$id, ...$this->descendantIds($node)], true)),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return list<string> */
    private function descendantIds(array $node): array
    {
        $ids = [];

        foreach ($node['branches'] ?? [] as $children) {
            foreach ($children as $child) {
                $ids = [...$ids, $child['id'], ...$this->descendantIds($child)];
            }
        }

        return $ids;
    }

    private function containsStep(array $node, string $id): bool
    {
        foreach ($node['branches'] ?? [] as $children) {
            foreach ($children as $child) {
                if ($child['id'] === $id || $this->containsStep($child, $id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Record the tree before a change, for undo, and mark the editor dirty. */
    private function remember(): void
    {
        $this->history[] = [$this->trigger, $this->steps];
        $this->history = array_slice($this->history, -self::HISTORY_LIMIT);
        $this->dirty = true;
    }

    /** @return array<string, string> brick key => label */
    private function labels(): array
    {
        return array_map(fn ($class) => (string) __($class::$label), app(BrickRegistry::class)->all());
    }

    /**
     * Steps whose settings are missing or invalid, with the first problem,
     * keyed by step id (the trigger's under `trigger`). Shown on the cards
     * as you edit, and checked again before publishing.
     *
     * @return array<string, string>
     */
    private function problems(): array
    {
        $registry = app(BrickRegistry::class);
        $problems = [];

        $check = function (array $nodes) use (&$check, &$problems, $registry): void {
            foreach ($nodes as $key => $node) {
                $id = $node['id'] ?? $key;

                if (! $registry->has($node['brick'] ?? '')) {
                    $problems[$id] = __('flowkit::errors.unknown_brick', ['where' => $id, 'brick' => $node['brick'] ?? '']);
                } else {
                    $validator = SettingsForm::for($registry->resolve($node['brick']), StepVariables::before($this->trigger, $this->steps, $id))
                        ->validator($node['settings'] ?? []);

                    if ($validator->fails()) {
                        $problems[$id] = $validator->errors()->first();
                    }
                }

                foreach ($node['branches'] ?? [] as $children) {
                    $check($children);
                }
            }
        };

        $check($this->trigger ? ['trigger' => $this->trigger] : []);
        $check($this->steps);

        return $problems;
    }

    private function assertBrick(string $key, ?string $type = null): void
    {
        $registry = app(BrickRegistry::class);

        abort_unless($registry->has($key), 422, "Unknown brick [{$key}].");

        if ($type) {
            abort_unless($registry->all()[$key]::type() === $type, 422, "[{$key}] is not a {$type}.");
        }
    }

    private function findStep(?string $id): ?array
    {
        return $id ? (new Definition($this->trigger, $this->steps))->step($id) : null;
    }

    private function mapTree(array $steps, callable $callback): array
    {
        return array_map(function (array $step) use ($callback) {
            $step = $callback($step);

            foreach ($step['branches'] ?? [] as $name => $children) {
                $step['branches'][$name] = $this->mapTree($children, $callback);
            }

            return $step;
        }, $steps);
    }

    private function filterTree(array $steps, callable $keep): array
    {
        $kept = [];

        foreach ($steps as $step) {
            if (! $keep($step)) {
                continue;
            }

            foreach ($step['branches'] ?? [] as $name => $children) {
                $step['branches'][$name] = $this->filterTree($children, $keep);
            }

            $kept[] = $step;
        }

        return $kept;
    }
}
