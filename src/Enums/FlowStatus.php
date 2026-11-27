<?php

namespace Act\Flowkit\Enums;

enum FlowStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Live = 'live';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending approval',
            self::Live => 'Live',
            self::Archived => 'Archived',
        };
    }
}
