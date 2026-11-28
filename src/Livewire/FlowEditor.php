<?php

namespace MightyMango\Flowkit\Livewire;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use MightyMango\Flowkit\Bricks\Brick;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Bricks\Core\Branch;
use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Exceptions\InvalidDefinition;
use MightyMango\Flowkit\Models\Flow;

/**
 * Loads a flow's draft version, holds the step tree, and adds, moves,
 * deletes and configures steps. Logic lives here; markup lives in the
 * publishable flowkit:: Blade components.
 */
class FlowEditor extends Component
{
    #[Locked]
    public int $flowId;

    public ?array $trigger = null;

    public array $steps = [];

    public ?string $selected = null;

    public array $settings = [];

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
            $this->settings = [];
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
        $this->resetValidation();

        $node = $id === 'trigger' ? $this->trigger : $this->findStep($id);
        $this->settings = $node['settings'] ?? [];
    }

    /**
     * Validate the selected step's settings server-side against its brick's
     * fields, then store them in the tree.
     */
    public function applySettings(): void
    {
        $node = $this->selected === 'trigger' ? $this->trigger : $this->findStep($this->selected);

        if (! $node) {
            return;
        }

        // Validate the settings on their own, exactly as the runner does, so
        // rules that refer to other settings (e.g. required_without) resolve.
        $this->resetValidation();
        $brick = app(BrickRegistry::class)->resolve($node['brick']);
        $validator = Validator::make(
            $this->settings,
            $brick->rulesFor($this->settings),
            attributes: array_column($this->fieldsFor($brick), 'label', 'name'),
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError("settings.{$key}", $message);
                }
            }

            return;
        }

        $validated = $validator->validated();

        if ($this->selected === 'trigger') {
            $this->trigger['settings'] = $validated;
        } else {
            $id = $this->selected;
            $this->steps = $this->mapTree($this->steps, function (array $step) use ($id, $validated) {
                if ($step['id'] === $id) {
                    $step['settings'] = $validated;
                }

                return $step;
            });
        }
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
        $brick = $node && $registry->has($node['brick']) ? $registry->resolve($node['brick']) : null;

        return view('flowkit::livewire.flow-editor', [
            'flow' => Flow::findOrFail($this->flowId),
            'triggers' => $registry->grouped('trigger'),
            'stepBricks' => array_merge_recursive($registry->grouped('action'), $registry->grouped('branch'), $registry->grouped('condition')),
            'labels' => array_map(fn ($class) => __($class::$label), $registry->all()),
            'selectedBrick' => $brick,
            'fields' => $brick ? $this->fieldsFor($brick) : [],
        ]);
    }

    /**
     * The selected brick's fields for the settings panel. A branch that uses
     * a Condition brick also shows that condition's fields, stored under
     * `condition_settings`.
     */
    private function fieldsFor(Brick $brick): array
    {
        $fields = array_map(fn ($field) => $field->toArray(), $brick->fields());

        if ($brick instanceof Branch) {
            foreach ($brick->conditionFields($this->settings) as $field) {
                $fields[] = ['name' => "condition_settings.{$field->name}"] + $field->toArray();
            }
        }

        return $fields;
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
