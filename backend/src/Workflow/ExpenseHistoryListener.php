<?php

namespace App\Workflow;

use App\Entity\Expense;
use App\Entity\ExpenseEvent;
use App\Enum\ExpenseEventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Inscrit chaque transition réussie dans l'historique de la note (C10). La création, qui n'est
 * pas une transition, est inscrite par ExpenseManager::create(). Le flush revient à l'appelant.
 */
#[AsCompletedListener(workflow: 'expense')]
final class ExpenseHistoryListener
{
    private const TYPES = [
        'submit' => ExpenseEventType::Submitted,
        'approve' => ExpenseEventType::Approved,
        'reject' => ExpenseEventType::Rejected,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CurrentActor $actor,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $expense = $event->getSubject();
        \assert($expense instanceof Expense);
        $type = self::TYPES[$event->getTransition()?->getName()] ?? throw new \LogicException('Transition sans type d\'historique.');

        $comment = ExpenseEventType::Rejected === $type ? $expense->getRejectionReason() : null;
        $this->em->persist(new ExpenseEvent($expense, $this->actor->get(), $type, $comment));
    }
}
