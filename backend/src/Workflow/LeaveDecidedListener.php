<?php

namespace App\Workflow;

use App\Entity\LeaveRequest;
use Psr\Clock\ClockInterface;
use Symfony\Component\Workflow\Attribute\AsEnteredListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Entrée dans « accepted » ou « refused » : enregistre qui a décidé et quand (spec 007, C7, C8),
 * et le motif d'un refus, transmis dans le contexte de la transition (clé « reason »).
 */
final class LeaveDecidedListener
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly CurrentActor $actor,
    ) {
    }

    #[AsEnteredListener(workflow: 'leave_request', place: 'accepted')]
    public function onAccepted(EnteredEvent $event): void
    {
        $this->decide($event);
    }

    #[AsEnteredListener(workflow: 'leave_request', place: 'refused')]
    public function onRefused(EnteredEvent $event): void
    {
        $reason = $event->getContext()['reason'] ?? null;
        if (!\is_string($reason)) {
            throw new \LogicException('Un refus exige un motif dans le contexte de la transition.');
        }
        $this->decide($event)->setRefusalReason($reason);
    }

    private function decide(EnteredEvent $event): LeaveRequest
    {
        $leave = $event->getSubject();
        \assert($leave instanceof LeaveRequest);

        return $leave->setDecidedAt($this->clock->now())->setDecidedBy($this->actor->get());
    }
}
