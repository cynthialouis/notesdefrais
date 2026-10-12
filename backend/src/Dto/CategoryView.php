<?php

namespace App\Dto;

use App\Entity\Category;

/**
 * Catégorie et sa politique de dépense (GET /api/categories, PUT /api/policies/{slug}).
 */
final readonly class CategoryView
{
    public function __construct(
        public string $slug,
        public string $label,
        /** Plafond par note en centimes ; null = aucun plafond */
        public ?int $maxAmountCents,
        public bool $receiptRequired,
    ) {
    }

    public static function fromEntity(Category $category): self
    {
        return new self($category->getSlug(), $category->getLabel(), $category->getMaxAmountCents(), $category->isReceiptRequired());
    }
}
