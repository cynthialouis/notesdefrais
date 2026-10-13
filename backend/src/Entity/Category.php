<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catégorie de dépense. Donnée de référence : les catégories sont insérées par migration.
 * Elle porte la politique de dépense de la catégorie (spec 004), réglée par l'admin.
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 50)]
    private string $label = '';

    /** Plafond par note, en centimes ; null = aucun plafond. */
    #[ORM\Column(nullable: true)]
    private ?int $maxAmountCents = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $receiptRequired = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getMaxAmountCents(): ?int
    {
        return $this->maxAmountCents;
    }

    public function setMaxAmountCents(?int $maxAmountCents): static
    {
        $this->maxAmountCents = $maxAmountCents;

        return $this;
    }

    public function isReceiptRequired(): bool
    {
        return $this->receiptRequired;
    }

    public function setReceiptRequired(bool $receiptRequired): static
    {
        $this->receiptRequired = $receiptRequired;

        return $this;
    }
}
