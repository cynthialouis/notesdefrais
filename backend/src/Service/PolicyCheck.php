<?php

namespace App\Service;

/**
 * Résultat du contrôle d'une note par rapport à la politique de dépense (spec 004).
 */
final readonly class PolicyCheck
{
    public function __construct(
        /** Plafond applicable, en centimes ; null = aucun plafond */
        public ?int $limitCents,
        /** Dépassement en centimes ; null si la note est conforme (montant ≤ plafond) ou sans plafond */
        public ?int $overLimitByCents,
        public bool $receiptRequired,
    ) {
    }
}
