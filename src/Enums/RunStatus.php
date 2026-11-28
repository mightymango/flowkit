<?php

namespace MightyMango\Flowkit\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return (string) __('flowkit::status.'.$this->value);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Skipped], true);
    }
}
