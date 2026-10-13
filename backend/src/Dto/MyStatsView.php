<?php

namespace App\Dto;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** Totaux personnels du tableau de bord (spec 006, C1, C2). */
final readonly class MyStatsView
{
    public function __construct(
        /** @var list<StatusTotalView> les quatre états, toujours présents, dans l'ordre du cycle de vie */
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: StatusTotalView::class)))]
        public array $byStatus,
    ) {
    }
}
