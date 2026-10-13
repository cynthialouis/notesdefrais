<?php

namespace App\Dto;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** Notes validées de l'équipe directe sur la période, par catégorie et par mois (spec 006, C3, C4). */
final readonly class TeamStatsView
{
    public function __construct(
        /** Premier jour de la période, AAAA-MM-JJ */
        public string $from,
        /** Dernier jour de la période, AAAA-MM-JJ */
        public string $to,
        public int $count,
        public int $totalCents,
        /** @var list<CategoryTotalView> toutes les catégories, dans l'ordre de référence */
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: CategoryTotalView::class)))]
        public array $byCategory,
        /** @var list<MonthTotalView> les 12 mois, du plus ancien au plus récent */
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: MonthTotalView::class)))]
        public array $byMonth,
    ) {
    }
}
