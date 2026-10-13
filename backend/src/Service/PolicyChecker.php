<?php

namespace App\Service;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;

/**
 * Seul endroit côté serveur qui dit si une note respecte la politique de dépense (spec 004, C7).
 * Plafond applicable : celui figé à la validation pour une note validée (C9), celui de la catégorie sinon.
 * Un montant égal au plafond est conforme (C5). Un justificatif obligatoire manque s'il est exigé par la
 * catégorie et absent d'une note encore modifiable (spec 005, C9).
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

        // Le manque ne vaut que pour une note encore modifiable, donc à soumettre (C9 précisé le 13/10)
        $receiptMissing = $receiptRequired && null === $expense->getReceipt() && $expense->getStatus()->isEditable();

        return new PolicyCheck($limit, $overBy, $receiptRequired, $receiptMissing);
    }
}
