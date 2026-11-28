<?php

namespace MightyMango\Flowkit\Engine;

use Illuminate\Support\Arr;
use LogicException;
use MightyMango\Flowkit\Models\FlowClaim;

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

    /**
     * Claim this run's step before doing something that must only happen
     * once, such as sending an email. Returns true the first time, and false
     * on every later attempt (a retry, or the same job running twice), so:
     *
     *   if (! $context->claimOnce()) {
     *       return Result::skip(['already_sent' => true]);
     *   }
     *
     * The claim is stored in the database, in the same place as the run,
     * so it doesn't depend on the app's cache store. Claim before the side
     * effect, not after it.
     */
    public function claimOnce(?string $stepId = null): bool
    {
        $stepId ??= $this->stepId ?? throw new LogicException('claimOnce() needs a step id: the runner has not said which step is running.');

        return FlowClaim::claim($this->idempotencyKey($stepId), $this->runId);
    }

    public function all(): array
    {
        return $this->data;
    }
}
