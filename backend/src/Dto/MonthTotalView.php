<?php

namespace App\Dto;

/** Notes validées de l'équipe datées d'un mois de la période (spec 006, C4). */
final readonly class MonthTotalView
{
    public function __construct(
        /** Mois au format AAAA-MM */
        public string $month,
        public int $count,
        public int $totalCents,
    ) {
    }
}
