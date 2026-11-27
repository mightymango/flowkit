<?php

namespace MightyMango\Flowkit\Engine;

use Illuminate\Support\Arr;

/**
 * The data a run carries from step to step. The trigger payload is merged in
 * at the top level; each step's output is stored under `steps.<id>` so later
 * steps can refer to it, e.g. `{{ steps.s3.message_id }}`.
 */
final class Context
{
    /** The step being run, when the runner has said which one it is. */
    private ?string $stepId = null;

    public function __construct(
        private array $data = [],
        public readonly ?int $runId = null,
        public readonly bool $dryRun = false,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    public function set(string $key, mixed $value): self
    {
        Arr::set($this->data, $key, $value);

        return $this;
    }

    public function recordOutput(string $stepId, array $output): self
    {
        $this->data['steps'][$stepId] = $output;

        return $this;
    }

    /**
     * Mark which step is about to run, so an action can find its own step
     * id, e.g. for idempotencyKey().
     */
    public function forStep(?string $stepId): self
    {
        $this->stepId = $stepId;

        return $this;
    }

    public function stepId(): ?string
    {
        return $this->stepId;
    }

    /**
     * A stable key for an action that isn't safe to retry, so a retried job
     * can tell it has already done its work.
     */
    public function idempotencyKey(string $stepId): string
    {
        return 'flowkit:'.($this->runId ?? 'adhoc').':'.$stepId;
    }

    public function all(): array
    {
        return $this->data;
    }
}
