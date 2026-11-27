<?php

namespace Act\Flowkit\Engine;

use Act\Flowkit\Enums\RunStatus;

/**
 * What a brick hands back to the runner.
 */
final class Result
{
    private function __construct(
        public readonly RunStatus $status,
        public readonly array $output = [],
        public readonly ?string $branch = null,
        public readonly ?string $waitFor = null,
        public readonly ?string $error = null,
    ) {}

    public static function ok(array $output = []): self
    {
        return new self(RunStatus::Completed, $output);
    }

    /**
     * Continue down the named branch of a branch step.
     */
    public static function branch(string $name, array $output = []): self
    {
        return new self(RunStatus::Completed, $output, branch: $name);
    }

    /**
     * Pause the run for an ISO 8601 duration, e.g. P7D.
     */
    public static function wait(string $duration): self
    {
        return new self(RunStatus::Waiting, ['duration' => $duration], waitFor: $duration);
    }

    public static function skip(array $output = []): self
    {
        return new self(RunStatus::Skipped, $output);
    }

    public static function fail(string $error, array $output = []): self
    {
        return new self(RunStatus::Failed, $output, error: $error);
    }
}
