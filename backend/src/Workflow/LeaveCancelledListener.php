<?php

namespace App\Workflow;

use App\Entity\LeaveRequest;
use Psr\Clock\ClockInterface;
use Symfony\Component\Workflow\Attribute\AsEnteredListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Entrée dans « cancelled » : enregistre qui a annulé, quand et pourquoi (spec 007, C13). Le motif
 * est transmis dans le contexte de la transition (clé « reason »). Une décision antérieure est conservée.
 */
final class LeaveCancelledListener
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly CurrentActor $actor,
    ) {
    }

    #[AsEnteredListener(workflow: 'leave_request', place: 'cancelled')]
    public function __invoke(EnteredEvent $event): void
    {
        $leave = $event->getSubject();
        \assert($leave instanceof LeaveRequest);
        $reason = $event->getContext()['reason'] ?? null;
        if (!\is_string($reason)) {
            throw new \LogicException('Une annulation exige un motif dans le contexte de la transition.');
        }

        $leave->setCancelledAt($this->clock->now())
            ->setCancelledBy($this->actor->get())
            ->setCancellationReason($reason);
    }
}
