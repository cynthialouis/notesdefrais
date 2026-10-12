<?php

namespace App\Enum;

/**
 * États d'une note de frais. Les transitions sont déclarées dans config/packages/workflow.yaml.
 */
enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Seul endroit qui dit quels états se modifient (et se suppriment) par leur propriétaire :
     * un brouillon, ou une note rejetée à corriger avant de la resoumettre (spec 003, C8).
     */
    public function isEditable(): bool
    {
        return self::Draft === $this || self::Rejected === $this;
    }
}
