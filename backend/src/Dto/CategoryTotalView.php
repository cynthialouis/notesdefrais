<?php

namespace App\Dto;

/** Notes validées de l'équipe dans une catégorie, sur la période (spec 006, C3). */
final readonly class CategoryTotalView
{
    public function __construct(
        public ExpenseCategoryRef $category,
        public int $count,
        public int $totalCents,
    ) {
    }
}
