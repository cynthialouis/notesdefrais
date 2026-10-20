<?php

namespace App\Service;

use App\Dto\LeaveRequestInput;
use App\Entity\LeaveRequest;
use App\Entity\User;
use App\Http\PublicHttpException;
use App\Repository\LeaveRequestRepository;
use App\Text\UnicodeTrim;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Règles d'écriture d'une demande de congé (spec 007). Le statut ne change que par le Workflow
 * « leave_request », dont les listeners enregistrent la décision et l'annulation. Les entrées sont
 * déjà validées (LeaveRequestInput, RejectionInput) et le droit sur la demande déjà vérifié
 * (LeaveRequestVoter, ROLE_ADMIN pour l'annulation).
 */
final class LeaveManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeaveRequestRepository $leaves,
        #[Target('leave_request')]
        private readonly WorkflowInterface $workflow,
    ) {
    }

    /**
     * Crée une demande en attente (C1). Après la validation des dates (400, faite à la réception),
     * refus sans responsable (C5), puis refus d'une période déjà occupée (C4) : research.md, D5.
     */
    public function request(User $requester, LeaveRequestInput $input): LeaveRequest
    {
        if (null === $requester->getManager()) {
            throw PublicHttpException::conflict('Vous n\'avez pas de responsable : personne ne peut accepter vos demandes de congé, vous ne pouvez donc pas en faire.');
        }

        $start = self::date((string) $input->startDate);
        $end = self::date((string) $input->endDate);
        if ($this->leaves->hasOverlap($requester, $start, $end)) {
            throw PublicHttpException::conflict('Cette période chevauche une autre de vos demandes, en attente ou acceptée.');
        }

        $leave = (new LeaveRequest())
            ->setRequester($requester)
            ->setStartDate($start)
            ->setEndDate($end);
        $this->em->persist($leave);
        $this->em->flush();

        return $leave;
    }

    /**
     * Retire une demande en attente (C11) ; refusé une fois la demande décidée ou annulée (C12).
     */
    public function withdraw(LeaveRequest $leave): LeaveRequest
    {
        if (!$this->workflow->can($leave, 'withdraw')) {
            throw PublicHttpException::conflict('Seule une demande en attente peut être retirée.');
        }
        $this->workflow->apply($leave, 'withdraw');
        $this->em->flush();

        return $leave;
    }

    /**
     * Accepte une demande en attente (C7) ; refusé si elle a déjà été décidée, retirée ou annulée (C10).
     */
    public function accept(LeaveRequest $leave): LeaveRequest
    {
        $this->decide($leave, 'accept');

        return $leave;
    }

    /**
     * Refuse une demande en attente avec un motif (C8), conservé sans les blancs autour ; même refus
     * que l'acceptation si elle n'est plus en attente (C10).
     *
     * @param string $reason motif déjà validé (RejectionInput, C9)
     */
    public function refuse(LeaveRequest $leave, string $reason): LeaveRequest
    {
        $this->decide($leave, 'refuse', ['reason' => UnicodeTrim::trim($reason)]);

        return $leave;
    }

    /**
     * Annule une demande en attente ou un congé accepté, quelle que soit sa date, avec un motif (C13) ;
     * refusé si la demande est refusée, retirée ou déjà annulée (C14). La décision d'origine est conservée.
     *
     * @param string $reason motif déjà validé (RejectionInput, C9)
     */
    public function cancel(LeaveRequest $leave, string $reason): LeaveRequest
    {
        if (!$this->workflow->can($leave, 'cancel')) {
            throw PublicHttpException::conflict('Seule une demande en attente ou un congé accepté peut être annulé.');
        }
        $this->workflow->apply($leave, 'cancel', ['reason' => UnicodeTrim::trim($reason)]);
        $this->em->flush();

        return $leave;
    }

    /** @param array<string, mixed> $context */
    private function decide(LeaveRequest $leave, string $transition, array $context = []): void
    {
        if (!$this->workflow->can($leave, $transition)) {
            throw PublicHttpException::conflict('Seule une demande en attente peut être acceptée ou refusée.');
        }
        $this->workflow->apply($leave, $transition, $context);
        $this->em->flush();
    }

    /**
     * Date Y-m-d déjà validée par LeaveRequestInput, à minuit, sans heure parasite.
     */
    private static function date(string $value): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value)
            ?: throw new \LogicException('Date non validée : '.$value);
    }
}
