<?php

namespace App\Service;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;

/**
 * Seul endroit côté serveur qui dit si une note respecte la politique de dépense (spec 004, C7).
 * Plafond applicable : celui figé à la validation pour une note validée (C9), celui de la catégorie sinon.
 * Un montant égal au plafond est conforme (C5). Un justificatif obligatoire manque s'il est exigé par la
 * catégorie et absent de la note (spec 005, C9).
 */
final class PolicyChecker
{
    public function check(Expense $expense): PolicyCheck
    {
        $limit = ExpenseStatus::Approved === $expense->getStatus()
            ? $expense->getLimitAtApprovalCents()
            : $expense->getCategory()->getMaxAmountCents();

        $overBy = null !== $limit && $expense->getAmountCents() > $limit ? $expense->getAmountCents() - $limit : null;

        $receiptRequired = $expense->getCategory()->isReceiptRequired();

        return new PolicyCheck($limit, $overBy, $receiptRequired, $receiptRequired && null === $expense->getReceipt());
    }
}
