<?php

namespace App\Entity;

use App\Enum\LeaveStatus;
use App\Repository\LeaveRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

/**
 * Demande de congé (spec 007), en journées entières, bornes comprises.
 * Le statut ne change que par le Workflow « leave_request ».
 */
#[ORM\Entity(repositoryClass: LeaveRequestRepository::class)]
#[ORM\Index(columns: ['requester_id', 'status'])]
#[ORM\Index(columns: ['status', 'start_date'])]
class LeaveRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $requester;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(enumType: LeaveStatus::class)]
    private LeaveStatus $status = LeaveStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $refusalReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $cancelledBy = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $cancellationReason = null;

    public function __construct()
    {
        $this->createdAt = self::utc(Clock::get()->now());
    }

    /**
     * Les instants sont stockés en UTC, comme dans Expense : Doctrine perd le décalage horaire.
     *
     * @return ($date is null ? null : \DateTimeImmutable)
     */
    private static function utc(?\DateTimeImmutable $date): ?\DateTimeImmutable
    {
        return $date?->setTimezone(new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequester(): User
    {
        return $this->requester;
    }

    public function setRequester(User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function isRequestedBy(User $user): bool
    {
        return $this->requester->getId() === $user->getId();
    }

    /**
     * Seul endroit qui dit qui décide d'une demande : le responsable direct du demandeur (C7, C8).
     */
    public function isDecidedBy(User $user): bool
    {
        $responsible = $this->requester->getManager();

        return null !== $responsible && $responsible->getId() === $user->getId();
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): LeaveStatus
    {
        return $this->status;
    }

    /**
     * Utilisé par le marking store du Workflow (et par les fixtures et les tests) :
     * le code métier passe par le Workflow, jamais par ce setter.
     */
    public function setStatus(LeaveStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function setDecidedAt(?\DateTimeImmutable $decidedAt): static
    {
        $this->decidedAt = self::utc($decidedAt);

        return $this;
    }

    public function getDecidedBy(): ?User
    {
        return $this->decidedBy;
    }

    public function setDecidedBy(?User $decidedBy): static
    {
        $this->decidedBy = $decidedBy;

        return $this;
    }

    public function getRefusalReason(): ?string
    {
        return $this->refusalReason;
    }

    public function setRefusalReason(?string $refusalReason): static
    {
        $this->refusalReason = $refusalReason;

        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = self::utc($cancelledAt);

        return $this;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?User $cancelledBy): static
    {
        $this->cancelledBy = $cancelledBy;

        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): static
    {
        $this->cancellationReason = $cancellationReason;

        return $this;
    }
}
