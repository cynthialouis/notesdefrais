<?php

namespace App\Service;

use OpenApi\Attributes as OA;

/**
 * Résultat du contrôle d'une note par rapport à la politique de dépense (spec 004).
 */
#[OA\Schema(required: ['limitCents', 'overLimitByCents'])] // nullable mais toujours présent (Nelmio ne le déduit pas)
final readonly class PolicyCheck
{
    public function __construct(
        /** Plafond applicable, en centimes ; null = aucun plafond */
        public ?int $limitCents,
        /** Dépassement en centimes ; null si la note est conforme (montant ≤ plafond) ou sans plafond */
        public ?int $overLimitByCents,
        public bool $receiptRequired,
        /** Justificatif obligatoire et absent d'une note modifiable : elle ne peut pas être soumise (spec 005, C8, C9) */
        public bool $receiptMissing,
    ) {
    }
}
