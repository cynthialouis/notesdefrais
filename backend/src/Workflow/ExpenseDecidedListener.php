<?php

namespace App\Workflow;

use App\Entity\Expense;
use Psr\Clock\ClockInterface;
use Symfony\Component\Workflow\Attribute\AsEnteredListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Entrée dans « approved » ou « rejected » : enregistre qui a décidé et quand (C3, C4),
 * et le motif d'un rejet, transmis dans le contexte de la transition (clé « reason »).
 */
final class ExpenseDecidedListener
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly CurrentActor $actor,
    ) {
    }

    #[AsEnteredListener(workflow: 'expense', place: 'approved')]
    public function onApproved(EnteredEvent $event): void
    {
        $this->decide($event);
    }

    #[AsEnteredListener(workflow: 'expense', place: 'rejected')]
    public function onRejected(EnteredEvent $event): void
    {
        $reason = $event->getContext()['reason'] ?? null;
        if (!\is_string($reason)) {
            throw new \LogicException('Un rejet exige un motif dans le contexte de la transition.');
        }
        $this->decide($event)->setRejectionReason($reason);
    }

    private function decide(EnteredEvent $event): Expense
    {
        $expense = $event->getSubject();
        \assert($expense instanceof Expense);

        return $expense->setDecidedAt($this->clock->now())->setDecidedBy($this->actor->get());
    }
}
