<?php

namespace MightyMango\Flowkit\Testing;

use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Models\FlowRun;
use MightyMango\Flowkit\Models\FlowRunStep;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * A finished (or waiting) run with assertions, as returned by runFlow().
 * Plain PHPUnit assertions, so it works with Pest or PHPUnit.
 *
 *   runFlow('new-starter', $payload)->assertCompleted()->assertStepCompleted('s3');
 */
class TestRun
{
    public function __construct(public readonly FlowRun $run) {}

    public function assertCompleted(): self
    {
        return $this->assertStatus(RunStatus::Completed);
    }

    public function assertFailed(): self
    {
        return $this->assertStatus(RunStatus::Failed);
    }

    public function assertWaiting(): self
    {
        return $this->assertStatus(RunStatus::Waiting);
    }

    public function assertStatus(RunStatus $status): self
    {
        PHPUnit::assertSame(
            $status, $this->run->status,
            "Expected the run to be [{$status->value}] but it is [{$this->run->status->value}]."
        );

        return $this;
    }

    public function assertStepCompleted(string $stepId): self
    {
        return $this->assertStepStatus($stepId, RunStatus::Completed);
    }

    public function assertStepSkipped(string $stepId): self
    {
        return $this->assertStepStatus($stepId, RunStatus::Skipped);
    }

    public function assertStepFailed(string $stepId, ?string $error = null): self
    {
        $this->assertStepStatus($stepId, RunStatus::Failed);

        if ($error !== null) {
            PHPUnit::assertStringContainsString($error, (string) $this->step($stepId)->error);
        }

        return $this;
    }

    public function assertStepWaiting(string $stepId): self
    {
        return $this->assertStepStatus($stepId, RunStatus::Waiting);
    }

    public function assertStepStatus(string $stepId, RunStatus $status): self
    {
        $step = $this->step($stepId);

        PHPUnit::assertSame(
            $status, $step->status,
            "Expected step [{$stepId}] to be [{$status->value}] but it is [{$step->status->value}]."
            .($step->error ? " Error: {$step->error}" : '')
        );

        return $this;
    }

    /**
     * The step never ran, e.g. it was on a branch the run didn't take.
     */
    public function assertStepNotReached(string $stepId): self
    {
        PHPUnit::assertNull($this->run->step($stepId), "Step [{$stepId}] was reached.");

        return $this;
    }

    public function assertStepOutput(string $stepId, string $key, mixed $value): self
    {
        $output = $this->step($stepId)->output ?? [];

        PHPUnit::assertArrayHasKey($key, $output, "Step [{$stepId}] has no output [{$key}].");
        PHPUnit::assertEquals($value, $output[$key], "Step [{$stepId}] output [{$key}] does not match.");

        return $this;
    }

    /**
     * @param  list<string>  $stepIds
     */
    public function assertStepsRan(array $stepIds): self
    {
        PHPUnit::assertSame($stepIds, $this->run->steps()->pluck('step_id')->all());

        return $this;
    }

    public function step(string $stepId): FlowRunStep
    {
        $step = $this->run->step($stepId);

        PHPUnit::assertNotNull($step, "Step [{$stepId}] was not reached.");

        return $step;
    }
}
