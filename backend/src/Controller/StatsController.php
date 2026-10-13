<?php

namespace App\Controller;

use App\Dto\MyStatsView;
use App\Dto\TeamStatsView;
use App\Entity\User;
use App\Stats\DashboardStats;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Totaux du tableau de bord (spec 006), calculés par DashboardStats.
 */
#[OA\Tag(name: 'Tableau de bord')]
#[OA\Response(response: 401, description: 'Non connecté')]
final class StatsController extends AbstractController
{
    public function __construct(private readonly DashboardStats $stats)
    {
    }

    #[Route('/api/stats/me', name: 'api_stats_me', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Nombre et total de ses notes pour chacun des quatre états', content: new Model(type: MyStatsView::class))]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->stats->forOwner($user));
    }

    #[Route('/api/stats/team', name: 'api_stats_team', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    #[OA\Response(response: 200, description: 'Notes validées de l\'équipe directe sur les 12 derniers mois civils, par catégorie et par mois', content: new Model(type: TeamStatsView::class))]
    #[OA\Response(response: 403, description: 'Réservé aux managers')]
    public function team(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->stats->forTeam($user));
    }
}
