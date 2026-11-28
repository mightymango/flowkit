<?php

namespace MightyMango\Flowkit\Enums;

enum FlowStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Live = 'live';
    case Archived = 'archived';

    public function label(): string
    {
        return (string) __('flowkit::status.'.$this->value);
    }
}
