<?php

namespace App\Workflow;

use App\Entity\Expense;
use Psr\Clock\ClockInterface;
use Symfony\Component\Workflow\Attribute\AsEnteredListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Date la soumission d'une note au moment où elle entre dans l'état « submitted » (C6).
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

        $expense->setSubmittedAt($this->clock->now());
    }
}
