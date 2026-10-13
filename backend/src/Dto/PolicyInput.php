<?php

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de PUT /api/policies/{slug}. Le plafond est reçu tel quel (mixed) pour qu'un flottant ou
 * une chaîne soit refusé par Assert\Type plutôt que converti (comme ExpenseInput::$amountCents).
 */
#[OA\Schema(required: ['receiptRequired'])]
final readonly class PolicyInput
{
    public function __construct(
        #[Assert\Type(type: 'integer', message: 'Le plafond doit être un nombre entier de centimes.')]
        #[Assert\Positive(message: 'Le plafond doit être positif.')]
        #[Assert\LessThanOrEqual(value: 1_000_000, message: 'Le plafond ne peut pas dépasser 10 000,00 €.')]
        #[OA\Property(type: 'integer', minimum: 1, maximum: 1_000_000, nullable: true, description: 'Plafond en centimes ; null = aucun plafond')]
        public mixed $maxAmountCents = null,
        #[Assert\NotNull(message: 'Indiquez si un justificatif est obligatoire.')]
        #[Assert\Type(type: 'bool', message: 'Valeur invalide.')]
        #[OA\Property(type: 'boolean', nullable: false)]
        public mixed $receiptRequired = null,
    ) {
    }
}
