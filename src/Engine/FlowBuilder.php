<?php

namespace Act\Flowkit\Engine;

use Act\Flowkit\Models\Flow;
use Closure;

/**
 * Code-first flows: builds the same definition JSON the editor produces.
 *
 *   Flowkit::define('new-starter')
 *       ->name('New starter')
 *       ->trigger('people.employee-created')
 *       ->branch('s1', "employee.department == 'Delivery'",
 *           yes: fn (FlowBuilder $b) => $b->step('s2', 'core.wait', ['duration' => 'P7D']),
 *       )
 *       ->step('s3', 'teams.message', ['to' => '{{ employee.manager_id }}'])
 *       ->save();
 */
class FlowBuilder
{
    private ?string $name = null;

    private ?array $trigger = null;

    private array $steps = [];

    public function __construct(private ?string $key = null) {}

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function trigger(string $brick, array $settings = []): self
    {
        $this->trigger = ['brick' => $brick, 'settings' => $settings];

        return $this;
    }

    public function step(string $id, string $brick, array $settings = []): self
    {
        $this->steps[] = ['id' => $id, 'brick' => $brick, 'settings' => $settings];

        return $this;
    }

    /**
     * @param  Closure(FlowBuilder): mixed|null  $yes
     * @param  Closure(FlowBuilder): mixed|null  $otherwise
     */
    public function branch(string $id, string $when, ?Closure $yes = null, ?Closure $otherwise = null): self
    {
        $this->steps[] = [
            'id' => $id,
            'brick' => 'core.branch',
            'settings' => ['when' => $when],
            'branches' => [
                'yes' => $this->nested($yes),
                'otherwise' => $this->nested($otherwise),
            ],
        ];

        return $this;
    }

    public function toDefinition(): Definition
    {
        return new Definition($this->trigger, $this->steps);
    }

    /**
     * Store the definition as a new published version of the flow, creating
     * the flow if needed. An unchanged definition isn't re-published.
     */
    public function save(): Flow
    {
        $definition = $this->toDefinition()->validate();

        $flow = Flow::firstOrCreate(['key' => $this->key], ['name' => $this->name ?? $this->key]);

        if ($this->name && $flow->name !== $this->name) {
            $flow->update(['name' => $this->name]);
        }

        if ($flow->liveVersion?->definition === $definition->toArray()) {
            return $flow;
        }

        $draft = $flow->draft();
        $draft->update(['definition' => $definition->toArray()]);
        $flow->publish($draft);

        return $flow->refresh();
    }

    private function nested(?Closure $callback): array
    {
        if (! $callback) {
            return [];
        }

        $builder = new self;
        $callback($builder);

        return $builder->steps;
    }
}
