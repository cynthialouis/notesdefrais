<?php

namespace App\Entity;

use App\Enum\ExpenseStatus;
use App\Repository\ExpenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

/**
 * Note de frais. Le montant est en centimes entiers, jamais en flottant.
 * Le statut ne change que par le Workflow « expense ».
 */
#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Index(columns: ['owner_id', 'status'])]
class Expense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    #[ORM\Column(length: 80)]
    private string $label = '';

    #[ORM\Column]
    private int $amountCents = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(enumType: ExpenseStatus::class)]
    private ExpenseStatus $status = ExpenseStatus::Draft;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $rejectionReason = null;

    /** Plafond de la catégorie au moment de la validation (spec 004, C9) ; null sinon ou sans plafond. */
    #[ORM\Column(nullable: true)]
    private ?int $limitAtApprovalCents = null;

    public function __construct()
    {
        $this->createdAt = self::utc(Clock::get()->now());
    }

    /**
     * Les instants sont stockés en UTC : Doctrine écrit une date dans le fuseau de l'objet sans le
     * décalage, puis la relit dans le fuseau par défaut (UTC). Une date en +02:00 serait décalée de 2 h.
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

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner->getId() === $user->getId();
    }

    /**
     * Seul endroit qui dit qui décide d'une note : le responsable direct de son propriétaire
     * (spec 003). Utilisé par ExpenseVoter (droits) et ExpenseView (ce que le lecteur peut faire).
     */
    public function isDecidedBy(User $user): bool
    {
        $responsible = $this->owner->getManager();

        return null !== $responsible && $responsible->getId() === $user->getId();
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function setAmountCents(int $amountCents): static
    {
        $this->amountCents = $amountCents;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStatus(): ExpenseStatus
    {
        return $this->status;
    }

    /**
     * Utilisé par le marking store du Workflow (et par les fixtures et les tests) :
     * le code métier passe par le Workflow, jamais par ce setter.
     */
    public function setStatus(ExpenseStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = self::utc($submittedAt);

        return $this;
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

    public function getLimitAtApprovalCents(): ?int
    {
        return $this->limitAtApprovalCents;
    }

    public function setLimitAtApprovalCents(?int $limitAtApprovalCents): static
    {
        $this->limitAtApprovalCents = $limitAtApprovalCents;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }
}
