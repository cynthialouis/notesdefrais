<?php

namespace App\Controller;

use App\Dto\ExpenseEventView;
use App\Dto\ExpenseInput;
use App\Dto\ExpenseListQuery;
use App\Dto\ExpenseView;
use App\Dto\RejectionInput;
use App\Entity\Expense;
use App\Entity\User;
use App\Repository\ExpenseEventRepository;
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
        return $this->json(array_map(static fn (Expense $expense): ExpenseView => ExpenseView::fromEntity($expense, $user), $repository->findForOwner($user, $query->status)));
    }

    #[Route('', name: 'api_expenses_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Brouillon créé', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 400, description: 'Champ invalide')]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ExpenseInput $input,
    ): JsonResponse {
        return $this->json(ExpenseView::fromEntity($this->expenses->create($user, $input), $user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_expenses_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ExpenseVoter::VIEW, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Note', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 404, description: 'Note inexistante ou d\'un autre utilisateur')]
    public function show(#[CurrentUser] User $user, #[MapEntity(id: 'id')] Expense $expense): JsonResponse
    {
        return $this->json(ExpenseView::fromEntity($expense, $user));
    }

    #[Route('/{id}', name: 'api_expenses_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Note en brouillon ou rejetée modifiée (tous les champs sont remplacés)', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 400, description: 'Champ invalide')]
    #[OA\Response(response: 404, description: 'Note inexistante ou d\'un autre utilisateur')]
    #[OA\Response(response: 409, description: 'La note n\'est ni en brouillon ni rejetée')]
    public function update(
        #[CurrentUser] User $user,
        #[MapEntity(id: 'id')] Expense $expense,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ExpenseInput $input,
    ): JsonResponse {
        return $this->json(ExpenseView::fromEntity($this->expenses->update($expense, $input), $user));
    }

    #[Route('/{id}/submit', name: 'api_expenses_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Note soumise', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 404, description: 'Note inexistante ou d\'un autre utilisateur')]
    #[OA\Response(response: 409, description: 'La note n\'est ni en brouillon ni rejetée, ou son propriétaire n\'a pas de responsable')]
    public function submit(#[CurrentUser] User $user, #[MapEntity(id: 'id')] Expense $expense): JsonResponse
    {
        return $this->json(ExpenseView::fromEntity($this->expenses->submit($expense), $user));
    }

    /** Valider : 404 si la note n'est pas visible (existence non révélée), 403 pour le propriétaire. */
    #[Route('/{id}/approve', name: 'api_expenses_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ExpenseVoter::VIEW, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[IsGranted(ExpenseVoter::DECIDE, subject: 'expense', statusCode: Response::HTTP_FORBIDDEN)]
    #[OA\Response(response: 200, description: 'Note validée', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 403, description: 'Le propriétaire ne décide pas de sa propre note')]
    #[OA\Response(response: 404, description: 'Note inexistante ou hors de l\'équipe')]
    #[OA\Response(response: 409, description: 'La note n\'est pas soumise')]
    public function approve(#[CurrentUser] User $user, #[MapEntity(id: 'id')] Expense $expense): JsonResponse
    {
        return $this->json(ExpenseView::fromEntity($this->expenses->approve($expense), $user));
    }

    #[Route('/{id}/reject', name: 'api_expenses_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ExpenseVoter::VIEW, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[IsGranted(ExpenseVoter::DECIDE, subject: 'expense', statusCode: Response::HTTP_FORBIDDEN)]
    #[OA\Response(response: 200, description: 'Note rejetée', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 400, description: 'Motif invalide')]
    #[OA\Response(response: 403, description: 'Le propriétaire ne décide pas de sa propre note')]
    #[OA\Response(response: 404, description: 'Note inexistante ou hors de l\'équipe')]
    #[OA\Response(response: 409, description: 'La note n\'est pas soumise')]
    public function reject(
        #[CurrentUser] User $user,
        #[MapEntity(id: 'id')] Expense $expense,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] RejectionInput $input,
    ): JsonResponse {
        \assert(null !== $input->reason);

        return $this->json(ExpenseView::fromEntity($this->expenses->reject($expense, $input->reason), $user));
    }

    #[Route('/{id}/events', name: 'api_expenses_events', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ExpenseVoter::VIEW, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Historique, du plus ancien au plus récent', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: ExpenseEventView::class))))]
    #[OA\Response(response: 404, description: 'Note inexistante ou non visible')]
    public function events(#[MapEntity(id: 'id')] Expense $expense, ExpenseEventRepository $events): JsonResponse
    {
        return $this->json(array_map(ExpenseEventView::fromEntity(...), $events->findForExpense($expense)));
    }

    #[Route('/{id}', name: 'api_expenses_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 204, description: 'Note en brouillon ou rejetée supprimée')]
    #[OA\Response(response: 404, description: 'Note inexistante ou d\'un autre utilisateur')]
    #[OA\Response(response: 409, description: 'La note n\'est ni en brouillon ni rejetée')]
    public function delete(#[MapEntity(id: 'id')] Expense $expense): Response
    {
        $this->expenses->delete($expense);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
