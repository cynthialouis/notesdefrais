<?php

namespace App\Dto;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;

/**
 * Note de frais telle que renvoyée au front.
 */
final readonly class ExpenseView
{
    public function __construct(
        public int $id,
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
        /** Vrai si le propriétaire peut encore la modifier ou la supprimer (ExpenseStatus::isEditable()) */
        public bool $editable,
    ) {
    }

    public static function fromEntity(Expense $expense): self
    {
        return new self(
            (int) $expense->getId(),
            $expense->getLabel(),
            $expense->getAmountCents(),
            $expense->getDate()->format('Y-m-d'),
            new CategoryView($expense->getCategory()->getSlug(), $expense->getCategory()->getLabel()),
            $expense->getStatus(),
            $expense->getCreatedAt()->format(\DATE_ATOM),
            $expense->getSubmittedAt()?->format(\DATE_ATOM),
            $expense->getStatus()->isEditable(),
        );
    }
}
