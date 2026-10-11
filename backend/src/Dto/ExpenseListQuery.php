<?php

namespace App\Dto;

use App\Enum\ExpenseStatus;

/**
 * Paramètres de GET /api/expenses : un état inconnu est refusé (400) à la désérialisation.
 */
final readonly class ExpenseListQuery
{
    public function __construct(
        public ?ExpenseStatus $status = null,
    ) {
    }
}
