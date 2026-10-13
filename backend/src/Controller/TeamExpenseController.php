<?php

namespace App\Controller;

use App\Dto\ExpenseView;
use App\Dto\ExpenseViewFactory;
use App\Entity\Expense;
use App\Entity\User;
use App\Repository\ExpenseRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TeamExpenseController extends AbstractController
{
    #[Route('/api/team/expenses', name: 'api_team_expenses', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    #[OA\Response(response: 200, description: 'Notes soumises de l\'équipe directe, les plus anciennes d\'abord', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ExpenseView::class))))]
    #[OA\Response(response: 401, description: 'Non connecté')]
    #[OA\Response(response: 403, description: 'Réservé aux managers')]
    public function __invoke(#[CurrentUser] User $user, ExpenseRepository $repository, ExpenseViewFactory $views): JsonResponse
    {
        return $this->json(array_map(
            static fn (Expense $expense): ExpenseView => $views->create($expense, $user),
            $repository->findSubmittedForResponsible($user),
        ));
    }
}
