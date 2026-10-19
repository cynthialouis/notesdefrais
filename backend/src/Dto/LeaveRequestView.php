<?php

namespace App\Dto;

use App\Entity\LeaveRequest;
use App\Entity\User;
use App\Enum\LeaveStatus;
use App\Enum\UserRole;
use OpenApi\Attributes as OA;

/**
 * Demande de congé telle que renvoyée au front, pour un lecteur donné (spec 007) : `decidable`,
 * `withdrawable` et `cancellable` disent ce que CE lecteur peut faire, le front n'a pas à recalculer
 * ces règles.
 */
#[OA\Schema(required: ['decidedAt', 'decidedBy', 'refusalReason', 'cancelledAt', 'cancelledBy', 'cancellationReason'])] // nullable mais toujours présent (Nelmio ne le déduit pas)
final readonly class LeaveRequestView
{
    public function __construct(
        public int $id,
        public PersonRef $requester,
        /** Premier jour du congé, au format Y-m-d */
        public string $startDate,
        /** Dernier jour du congé (compris), au format Y-m-d */
        public string $endDate,
        public LeaveStatus $status,
        /** Date et heure ISO 8601 de la demande */
        public string $createdAt,
        /** Date et heure ISO 8601 de l'acceptation ou du refus, null sinon */
        public ?string $decidedAt,
        public ?PersonRef $decidedBy,
        /** Motif du refus, null si la demande n'est pas refusée */
        public ?string $refusalReason,
        /** Date et heure ISO 8601 de l'annulation, null sinon */
        public ?string $cancelledAt,
        public ?PersonRef $cancelledBy,
        /** Motif de l'annulation, null si la demande n'est pas annulée */
        public ?string $cancellationReason,
        /** Vrai si le lecteur est le responsable du demandeur et peut accepter ou refuser */
        public bool $decidable,
        /** Vrai si le lecteur est le demandeur et peut encore retirer sa demande */
        public bool $withdrawable,
        /** Vrai si le lecteur est l'admin et peut annuler la demande ou le congé */
        public bool $cancellable,
    ) {
    }

    public static function fromEntity(LeaveRequest $leave, User $viewer): self
    {
        $status = $leave->getStatus();

        return new self(
            (int) $leave->getId(),
            PersonRef::fromUser($leave->getRequester()),
            $leave->getStartDate()->format('Y-m-d'),
            $leave->getEndDate()->format('Y-m-d'),
            $status,
            $leave->getCreatedAt()->format(\DATE_ATOM),
            $leave->getDecidedAt()?->format(\DATE_ATOM),
            null !== $leave->getDecidedBy() ? PersonRef::fromUser($leave->getDecidedBy()) : null,
            $leave->getRefusalReason(),
            $leave->getCancelledAt()?->format(\DATE_ATOM),
            null !== $leave->getCancelledBy() ? PersonRef::fromUser($leave->getCancelledBy()) : null,
            $leave->getCancellationReason(),
            $leave->isDecidedBy($viewer) && LeaveStatus::Pending === $status,
            $leave->isRequestedBy($viewer) && LeaveStatus::Pending === $status,
            UserRole::Admin === $viewer->getRole() && (LeaveStatus::Pending === $status || LeaveStatus::Accepted === $status),
        );
    }
}
