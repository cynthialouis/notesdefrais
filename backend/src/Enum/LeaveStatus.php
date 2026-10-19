<?php

namespace App\Enum;

/**
 * États d'une demande de congé (spec 007). Les transitions sont déclarées dans
 * config/packages/workflow.yaml (workflow « leave_request »).
 */
enum LeaveStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Withdrawn = 'withdrawn';
    case Cancelled = 'cancelled';

    /**
     * Seul endroit qui dit quelles demandes occupent leur période : une nouvelle demande du même
     * demandeur ne peut pas la chevaucher (C4). Refusée, retirée ou annulée, elle la libère.
     */
    public function blocksPeriod(): bool
    {
        return self::Pending === $this || self::Accepted === $this;
    }
}
