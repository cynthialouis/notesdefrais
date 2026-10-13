<?php

namespace App\Dto;

use App\Enum\ExpenseStatus;

/** Nombre et total des notes dans un état (spec 006, C1). */
final readonly class StatusTotalView
{
    public function __construct(
        public ExpenseStatus $status,
        public int $count,
        public int $totalCents,
    ) {
    }
}
