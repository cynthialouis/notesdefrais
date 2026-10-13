<?php

namespace App\Dto;

use App\Entity\Category;

/**
 * Catégorie telle qu'elle apparaît dans une note : identifiant et libellé seulement
 * (la politique de la note est dans ExpenseView::$policy).
 */
final readonly class ExpenseCategoryRef
{
    public function __construct(
        public string $slug,
        public string $label,
    ) {
    }

    public static function fromEntity(Category $category): self
    {
        return new self($category->getSlug(), $category->getLabel());
    }
}
