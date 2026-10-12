<?php

namespace App\Enum;

/**
 * Action inscrite dans l'historique d'une note (spec 003, C10).
 */
enum ExpenseEventType: string
{
    case Created = 'created';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
