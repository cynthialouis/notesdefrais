<?php

namespace App\Controller;

use App\Dto\ExpenseInput;
use App\Dto\ExpenseListQuery;
use App\Dto\ExpenseView;
use App\Entity\Expense;
use App\Entity\User;
use App\Repository\ExpenseRepository;
use App\Security\Voter\ExpenseVoter;
use App\Service\ExpenseManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Notes de frais de l'utilisateur courant. Une note d'un autre utilisateur répond 404,
 * comme une note inexistante : son existence n'est pas révélée.
 */
#[Route('/api/expenses')]
#[OA\Response(response: 401, description: 'Non connecté')]
final class ExpenseController extends AbstractController
{
    public function __construct(private readonly ExpenseManager $expenses)
    {
    }

    #[Route('', name: 'api_expenses_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Notes de l\'utilisateur, les plus récentes d\'abord', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ExpenseView::class))))]
    #[OA\Response(response: 400, description: 'État inconnu')]
    public function list(
        #[CurrentUser] User $user,
        ExpenseRepository $repository,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST, mapWhenEmpty: true)] ExpenseListQuery $query,
    ): JsonResponse {
        return $this->json(array_map(ExpenseView::fromEntity(...), $repository->findForOwner($user, $query->status)));
    }

    #[Route('', name: 'api_expenses_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Brouillon créé', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 400, description: 'Champ invalide')]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ExpenseInput $input,
    ): JsonResponse {
        return $this->json(ExpenseView::fromEntity($this->expenses->create($user, $input)), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_expenses_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Note', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 404, description: 'Note inexistante ou d\'un autre utilisateur')]
    public function show(#[MapEntity(id: 'id')] Expense $expense): JsonResponse
    {
        return $this->json(ExpenseView::fromEntity($expense));
    }

    #[Route('/{id}', name: 'api_expenses_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Brouillon modifié (tous les champs sont remplacés)', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 400, description: 'Champ invalide')]
    #[OA\Response(response: 404, description: 'Note inexistante ou d\'un autre utilisateur')]
    #[OA\Response(response: 409, description: 'La note n\'est plus en brouillon')]
    public function update(
        #[MapEntity(id: 'id')] Expense $expense,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ExpenseInput $input,
    ): JsonResponse {
        return $this->json(ExpenseView::fromEntity($this->expenses->update($expense, $input)));
    }

    #[Route('/{id}/submit', name: 'api_expenses_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Note soumise', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 404, description: 'Note inexistante ou d\'un autre utilisateur')]
    #[OA\Response(response: 409, description: 'La note n\'est plus en brouillon')]
    public function submit(#[MapEntity(id: 'id')] Expense $expense): JsonResponse
    {
        return $this->json(ExpenseView::fromEntity($this->expenses->submit($expense)));
    }

    #[Route('/{id}', name: 'api_expenses_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 204, description: 'Brouillon supprimé')]
    #[OA\Response(response: 404, description: 'Note inexistante ou d\'un autre utilisateur')]
    #[OA\Response(response: 409, description: 'La note n\'est plus en brouillon')]
    public function delete(#[MapEntity(id: 'id')] Expense $expense): Response
    {
        $this->expenses->delete($expense);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
