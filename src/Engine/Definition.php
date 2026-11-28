<?php

namespace MightyMango\Flowkit\Engine;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use MightyMango\Flowkit\Bricks\BrickRegistry;
use MightyMango\Flowkit\Exceptions\InvalidDefinition;

/**
 * A flow definition: a trigger plus an ordered list of steps. A branch step
 * holds named child step lists, e.g. `yes` and `otherwise`.
 *
 *   {
 *     "trigger": { "brick": "app.employee-created", "settings": {} },
 *     "steps": [
 *       { "id": "s1", "brick": "core.branch", "settings": { "when": "..." },
 *         "branches": { "yes": [...], "otherwise": [...] } }
 *     ]
 *   }
 */
final class Definition implements Arrayable, JsonSerializable
{
    /**
     * @param  array{brick?: string, settings?: array}|null  $trigger
     * @param  list<array>  $steps
     */
    public function __construct(
        public readonly ?array $trigger,
        public readonly array $steps,
    ) {}

    public static function empty(): self
    {
        return new self(null, []);
    }

    public static function fromArray(array $data): self
    {
        return new self($data['trigger'] ?? null, array_values($data['steps'] ?? []));
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * Every step in the tree, depth first, keyed by step ID.
     *
     * @return array<string, array>
     */
    public function allSteps(): array
    {
        $found = [];

        $walk = function (array $steps) use (&$walk, &$found) {
            foreach ($steps as $step) {
                $found[$step['id'] ?? '#'.count($found)] = $step;

                foreach ($step['branches'] ?? [] as $children) {
                    $walk($children);
                }
            }
        };

        $walk($this->steps);

        return $found;
    }

    public function step(string $id): ?array
    {
        return $this->allSteps()[$id] ?? null;
    }

    /**
     * Check the structure, and that every brick exists when a registry is given.
     *
     * @throws InvalidDefinition
     */
    public function validate(?BrickRegistry $bricks = null): self
    {
        $bricks ??= app(BrickRegistry::class);
        $errors = [];
        $seen = [];

        if ($this->trigger && ! $bricks->has($this->trigger['brick'] ?? '')) {
            $errors[] = __('flowkit::errors.unknown_trigger', ['brick' => $this->trigger['brick'] ?? '']);
        }

        $walk = function (array $steps, string $path) use (&$walk, &$errors, &$seen, $bricks) {
            foreach ($steps as $i => $step) {
                $where = "{$path}[{$i}]";

                if (empty($step['id'])) {
                    $errors[] = __('flowkit::errors.missing_id', ['where' => $where]);
                } elseif (isset($seen[$step['id']])) {
                    $errors[] = __('flowkit::errors.duplicate_id', ['id' => $step['id']]);
                } else {
                    $seen[$step['id']] = true;
                }

                if (empty($step['brick'])) {
                    $errors[] = __('flowkit::errors.missing_brick', ['where' => $where]);
                } elseif (! $bricks->has($step['brick'])) {
                    $errors[] = __('flowkit::errors.unknown_brick', ['brick' => $step['brick'], 'where' => $where]);
                }

                $condition = $step['settings']['condition'] ?? null;
                if (is_string($condition) && $condition !== ''
                    && ! array_key_exists($condition, $bricks->all('condition'))) {
                    $errors[] = __('flowkit::errors.unknown_condition', ['condition' => $condition, 'where' => $where]);
                }

                foreach ($step['branches'] ?? [] as $name => $children) {
                    $walk($children, "{$where}.branches.{$name}");
                }
            }
        };

        $walk($this->steps, 'steps');

        if ($errors) {
            throw new InvalidDefinition($errors);
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'trigger' => $this->trigger,
            'steps' => $this->steps,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
