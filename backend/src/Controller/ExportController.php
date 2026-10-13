<?php

namespace App\Controller;

use App\Entity\User;
use App\Stats\DashboardStats;
use App\Stats\TeamExpensesCsv;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Export des notes validées de l'équipe directe sur la période du tableau de bord (spec 006, C6 à C9).
 */
#[OA\Tag(name: 'Tableau de bord')]
final class ExportController extends AbstractController
{
    #[Route('/api/exports/team-expenses.csv', name: 'api_exports_team_expenses', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    #[OA\Response(response: 200, description: 'CSV pour tableur français (BOM UTF-8, « ; », montants à virgule)', content: new OA\MediaType(mediaType: 'text/csv', schema: new OA\Schema(type: 'string')))]
    #[OA\Response(response: 401, description: 'Non connecté')]
    #[OA\Response(response: 403, description: 'Réservé aux managers')]
    public function teamExpenses(#[CurrentUser] User $user, DashboardStats $stats, TeamExpensesCsv $csv, ClockInterface $clock): Response
    {
        $today = $clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');

        return new Response($csv->build($stats->teamExpenses($user)), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            // Écrit tel quel : nom ASCII sans caractère à échapper, entre guillemets comme l'attendent les tableurs
            'Content-Disposition' => \sprintf('attachment; filename="notes-validees-equipe-%s.csv"', $today),
        ]);
    }
}
