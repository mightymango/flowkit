<?php

namespace MightyMango\Flowkit\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Core\Branch;
use MightyMango\Flowkit\Bricks\TriggerVariables;
use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Exceptions\InvalidDefinition;
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

    public ?array $trigger = null;

    public array $steps = [];

    public ?string $selected = null;

    public array $errorsList = [];

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

        $this->steps = $move($this->steps);
    }

    public function select(?string $id): void
    {
        $this->selected = $id;
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
        $variables = $stepId === 'trigger' ? [] : TriggerVariables::for($this->trigger);
        $validator = SettingsForm::for($brick, $variables)->validator($settings);

        if ($validator->fails()) {
            return;
        }

        $validated = $validator->validated();

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

        $this->errorsList = [];
        $this->dispatch('flowkit-saved');
    }

    public function publish(): void
    {
        $this->save();

        $flow = Flow::findOrFail($this->flowId);

        try {
            $flow->publish($flow->draft(), auth()->user());
        } catch (InvalidDefinition $e) {
            $this->errorsList = $e->errors;

            return;
        }

        $this->dispatch('flowkit-published');
    }

    public function render()
    {
        $registry = app(BrickRegistry::class);
        $node = $this->selected === 'trigger' ? $this->trigger : $this->findStep($this->selected);

        return view('flowkit::livewire.flow-editor', [
            'flow' => Flow::findOrFail($this->flowId),
            'triggers' => $registry->grouped('trigger'),
            'stepBricks' => array_merge_recursive($registry->grouped('action'), $registry->grouped('branch'), $registry->grouped('condition')),
            'labels' => array_map(fn ($class) => __($class::$label), $registry->all()),
            'summaries' => $this->summaries(),
            'selectedNode' => $node && $registry->has($node['brick']) ? $node : null,
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
        $variables = TriggerVariables::for($this->trigger);
        $summaries = [];

        $collect = function (array $nodes) use (&$collect, &$summaries, $registry, $variables): void {
            foreach ($nodes as $id => $node) {
                if ($registry->has($node['brick'] ?? '')) {
                    $summaries[$node['id'] ?? $id] = $registry->resolve($node['brick'])
                        ->summary($node['settings'] ?? [], $id === 'trigger' ? [] : $variables);
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
