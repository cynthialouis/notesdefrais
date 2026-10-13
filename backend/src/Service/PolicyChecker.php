<?php

namespace App\Service;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;

/**
 * Seul endroit côté serveur qui dit si une note respecte la politique de dépense (spec 004, C7).
 * Plafond applicable : celui figé à la validation pour une note validée (C9), celui de la catégorie sinon.
 * Un montant égal au plafond est conforme (C5).
 */
final class PolicyChecker
{
    public function check(Expense $expense): PolicyCheck
    {
        $limit = ExpenseStatus::Approved === $expense->getStatus()
            ? $expense->getLimitAtApprovalCents()
            : $expense->getCategory()->getMaxAmountCents();

        $overBy = null !== $limit && $expense->getAmountCents() > $limit ? $expense->getAmountCents() - $limit : null;

        return new PolicyCheck($limit, $overBy, $expense->getCategory()->isReceiptRequired());
    }
}
