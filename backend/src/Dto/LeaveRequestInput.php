<?php

namespace App\Dto;

use App\Validator\NotInPast;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Corps de POST /api/leaves (spec 007, C2) : journées entières, bornes comprises.
 * Les champs sont nullables pour qu'un champ absent soit signalé par une contrainte (400 sur ce champ)
 * plutôt que par une erreur de type.
 */
final readonly class LeaveRequestInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
        #[Assert\Date(message: 'Date invalide (format attendu : AAAA-MM-JJ).')]
        #[NotInPast(message: 'Le congé ne peut pas commencer dans le passé.')]
        public ?string $startDate = null,

        #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
        #[Assert\Date(message: 'Date invalide (format attendu : AAAA-MM-JJ).')]
        public ?string $endDate = null,
    ) {
    }

    /**
     * Fin pas antérieure au début, vérifiée seulement si les deux dates sont valides : sinon, l'erreur
     * de format suffit sur le champ en cause.
     */
    #[Assert\Callback]
    public function validateEndNotBeforeStart(ExecutionContextInterface $context): void
    {
        if (!self::isDate($this->startDate) || !self::isDate($this->endDate)) {
            return;
        }
        // Deux dates Y-m-d se comparent comme des chaînes
        if ($this->endDate < $this->startDate) {
            $context->buildViolation('La date de fin ne peut pas être antérieure à la date de début.')
                ->atPath('endDate')
                ->addViolation();
        }
    }

    private static function isDate(?string $value): bool
    {
        return null !== $value
            && 1 === preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
