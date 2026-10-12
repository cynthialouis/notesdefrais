<?php

namespace App\Workflow;

use App\Entity\Expense;
use Psr\Clock\ClockInterface;
use Symfony\Component\Workflow\Attribute\AsEnteredListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Entrée dans « submitted » : date la soumission (002, C6) et efface la décision précédente
 * d'une note rejetée puis resoumise ; le motif reste dans l'historique (003, C8).
 */
#[AsEnteredListener(workflow: 'expense', place: 'submitted')]
final class ExpenseSubmittedListener
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function __invoke(EnteredEvent $event): void
    {
        $expense = $event->getSubject();
        \assert($expense instanceof Expense);

        $expense
            ->setSubmittedAt($this->clock->now())
            ->setDecidedAt(null)
            ->setDecidedBy(null)
            ->setRejectionReason(null);
    }
}
