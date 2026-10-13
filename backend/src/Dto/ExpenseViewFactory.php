<?php

namespace App\Dto;

use App\Entity\Expense;
use App\Entity\User;
use App\Service\PolicyChecker;

/**
 * Construit la vue d'une note pour un lecteur, avec son contrôle de politique (spec 004).
 */
final class ExpenseViewFactory
{
    public function __construct(private readonly PolicyChecker $policyChecker)
    {
    }

    public function create(Expense $expense, User $viewer): ExpenseView
    {
        return ExpenseView::fromEntity($expense, $viewer, $this->policyChecker->check($expense));
    }
}
