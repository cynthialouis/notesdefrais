<?php

namespace App\Entity;

use App\Enum\ExpenseEventType;
use App\Repository\ExpenseEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

/**
 * Entrée de l'historique d'une note : qui a fait quoi, quand, et pourquoi pour un rejet.
 * Jamais modifiée après sa création.
 */
#[ORM\Entity(repositoryClass: ExpenseEventRepository::class)]
#[ORM\Index(columns: ['expense_id', 'created_at'])]
class ExpenseEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Expense::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Expense $expense,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false)]
        private User $actor,
        #[ORM\Column(enumType: ExpenseEventType::class)]
        private ExpenseEventType $type,
        #[ORM\Column(length: 1000, nullable: true)]
        private ?string $comment = null,
    ) {
        $this->createdAt = Clock::get()->now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpense(): Expense
    {
        return $this->expense;
    }

    public function getActor(): User
    {
        return $this->actor;
    }

    public function getType(): ExpenseEventType
    {
        return $this->type;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
