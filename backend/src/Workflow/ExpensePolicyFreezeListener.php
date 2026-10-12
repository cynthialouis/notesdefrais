<?php

namespace App\Workflow;

use App\Entity\Expense;
use Symfony\Component\Workflow\Attribute\AsEnteredListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Entrée dans « approved » : fige le plafond en vigueur, pour qu'une décision ne change pas
 * après coup si l'admin modifie la politique (spec 004, C9).
 */
#[AsEnteredListener(workflow: 'expense', place: 'approved')]
final class ExpensePolicyFreezeListener
{
    public function __invoke(EnteredEvent $event): void
    {
        $expense = $event->getSubject();
        \assert($expense instanceof Expense);

        $expense->setLimitAtApprovalCents($expense->getCategory()->getMaxAmountCents());
    }
}
