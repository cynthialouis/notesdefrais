<?php

namespace App\Dto;

use App\Entity\Expense;
use App\Entity\User;
use App\Enum\ExpenseStatus;

/**
 * Note de frais telle que renvoyée au front, pour un lecteur donné : `editable` et `decidable`
 * disent ce que CE lecteur peut faire, le front n'a pas à recalculer ces règles.
 */
final readonly class ExpenseView
{
    public function __construct(
        public int $id,
        public PersonRef $owner,
        public string $label,
        public int $amountCents,
        /** Date de la dépense, au format Y-m-d */
        public string $date,
        public CategoryView $category,
        public ExpenseStatus $status,
        /** Date et heure ISO 8601 */
        public string $createdAt,
        /** Date et heure ISO 8601, null tant que la note n'est pas soumise */
        public ?string $submittedAt,
        /** Date et heure ISO 8601 de la validation ou du rejet, null sinon */
        public ?string $decidedAt,
        public ?PersonRef $decidedBy,
        /** Motif du rejet, null si la note n'est pas rejetée */
        public ?string $rejectionReason,
        /** Vrai si le lecteur est le propriétaire et peut encore la modifier ou la supprimer */
        public bool $editable,
        /** Vrai si le lecteur est le responsable du propriétaire et peut la valider ou la rejeter */
        public bool $decidable,
    ) {
    }

    public static function fromEntity(Expense $expense, User $viewer): self
    {
        $owner = $expense->getOwner();
        $isOwner = $owner->getId() === $viewer->getId();
        $isResponsible = null !== $owner->getManager() && $owner->getManager()->getId() === $viewer->getId();

        return new self(
            (int) $expense->getId(),
            PersonRef::fromUser($owner),
            $expense->getLabel(),
            $expense->getAmountCents(),
            $expense->getDate()->format('Y-m-d'),
            new CategoryView($expense->getCategory()->getSlug(), $expense->getCategory()->getLabel()),
            $expense->getStatus(),
            $expense->getCreatedAt()->format(\DATE_ATOM),
            $expense->getSubmittedAt()?->format(\DATE_ATOM),
            $expense->getDecidedAt()?->format(\DATE_ATOM),
            null !== $expense->getDecidedBy() ? PersonRef::fromUser($expense->getDecidedBy()) : null,
            $expense->getRejectionReason(),
            $isOwner && $expense->getStatus()->isEditable(),
            $isResponsible && ExpenseStatus::Submitted === $expense->getStatus(),
        );
    }
}
