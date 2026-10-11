<?php

namespace App\Dto;

use App\Validator\ExistingCategory;
use App\Validator\NotInFuture;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de création et de modification d'une note (POST et PATCH : tous les champs).
 * Les champs sont nullables pour qu'un champ absent soit signalé par une contrainte (400 sur ce champ)
 * plutôt que par une erreur de type. Le montant est reçu tel quel (mixed) et c'est Assert\Type qui
 * exige un entier : typé int, PHP tronquerait 1250.5 en 1250 avant toute validation ; typé int|float,
 * le serializer convertirait tous les entiers en flottants.
 */
final readonly class ExpenseInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé est obligatoire.', normalizer: 'trim')]
        #[Assert\Length(min: 2, max: 80, minMessage: 'Le libellé doit faire au moins {{ limit }} caractères.', maxMessage: 'Le libellé doit faire au plus {{ limit }} caractères.', normalizer: 'trim')]
        public ?string $label = null,

        #[Assert\NotNull(message: 'Le montant est obligatoire.')]
        #[Assert\Type(type: 'integer', message: 'Le montant doit être un nombre entier de centimes.')]
        #[Assert\Positive(message: 'Le montant doit être positif.')]
        #[Assert\LessThanOrEqual(value: 1_000_000, message: 'Le montant ne peut pas dépasser 10 000,00 €.')]
        public mixed $amountCents = null,

        #[Assert\NotBlank(message: 'La date est obligatoire.')]
        #[Assert\Date(message: 'Date invalide (format attendu : AAAA-MM-JJ).')]
        #[NotInFuture]
        public ?string $date = null,

        #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
        #[ExistingCategory]
        public ?string $categorySlug = null,
    ) {
    }
}
