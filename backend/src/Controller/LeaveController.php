<?php

namespace App\Controller;

use App\Dto\LeaveRequestInput;
use App\Dto\LeaveRequestView;
use App\Dto\RejectionInput;
use App\Entity\LeaveRequest;
use App\Entity\User;
use App\Repository\LeaveRequestRepository;
use App\Security\Voter\LeaveRequestVoter;
use App\Service\LeaveManager;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Demandes de congé (spec 007). Une demande que le lecteur n'a pas à connaître répond 404, comme une
 * demande inexistante : son existence n'est pas révélée.
 */
#[OA\Response(response: 401, description: 'Non connecté')]
final class LeaveController extends AbstractController
{
    public function __construct(private readonly LeaveManager $leaves)
    {
    }

    #[Route('/api/leaves', name: 'api_leaves_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Demande créée, en attente', content: new Model(type: LeaveRequestView::class))]
    #[OA\Response(response: 400, description: 'Date absente ou invalide, début dans le passé, fin avant le début')]
    #[OA\Response(response: 409, description: 'Demandeur sans responsable, ou période qui chevauche une autre de ses demandes en attente ou acceptée')]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] LeaveRequestInput $input,
    ): JsonResponse {
        return $this->json(LeaveRequestView::fromEntity($this->leaves->request($user, $input), $user), Response::HTTP_CREATED);
    }

    /** « Aujourd'hui » s'entend en heure de Paris (C15). */
    #[Route('/api/leaves', name: 'api_leaves_accepted', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Congés acceptés en cours ou à venir de tout le monde, date de début croissante', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: LeaveRequestView::class))))]
    public function accepted(#[CurrentUser] User $user, LeaveRequestRepository $repository, ClockInterface $clock): JsonResponse
    {
        $today = $clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);

        return $this->list($repository->findAcceptedEndingFrom($today), $user);
    }

    #[Route('/api/me/leaves', name: 'api_leaves_mine', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Toutes ses demandes, quel que soit leur état, date de début décroissante', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: LeaveRequestView::class))))]
    public function mine(#[CurrentUser] User $user, LeaveRequestRepository $repository): JsonResponse
    {
        return $this->list($repository->findForRequester($user), $user);
    }

    #[Route('/api/leaves/{id}/withdraw', name: 'api_leaves_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(LeaveRequestVoter::REQUESTER, subject: 'leave', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Demande retirée', content: new Model(type: LeaveRequestView::class))]
    #[OA\Response(response: 404, description: 'Demande inexistante ou d\'un autre utilisateur')]
    #[OA\Response(response: 409, description: 'La demande n\'est plus en attente')]
    public function withdraw(#[CurrentUser] User $user, #[MapEntity(id: 'id')] LeaveRequest $leave): JsonResponse
    {
        return $this->json(LeaveRequestView::fromEntity($this->leaves->withdraw($leave), $user));
    }

    #[Route('/api/team/leaves', name: 'api_leaves_team', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    #[OA\Response(response: 200, description: 'Demandes en attente de l\'équipe directe, date de début la plus proche d\'abord', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: LeaveRequestView::class))))]
    #[OA\Response(response: 403, description: 'Réservé aux managers')]
    public function team(#[CurrentUser] User $user, LeaveRequestRepository $repository): JsonResponse
    {
        return $this->list($repository->findPendingForResponsible($user), $user);
    }

    /** Accepter : 404 si le lecteur n'est ni le demandeur ni son responsable, 403 pour le demandeur. */
    #[Route('/api/leaves/{id}/accept', name: 'api_leaves_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(LeaveRequestVoter::PARTY, subject: 'leave', statusCode: Response::HTTP_NOT_FOUND)]
    #[IsGranted(LeaveRequestVoter::DECIDE, subject: 'leave', statusCode: Response::HTTP_FORBIDDEN)]
    #[OA\Response(response: 200, description: 'Demande acceptée', content: new Model(type: LeaveRequestView::class))]
    #[OA\Response(response: 403, description: 'Le demandeur ne décide pas de sa propre demande')]
    #[OA\Response(response: 404, description: 'Demande inexistante ou hors de l\'équipe')]
    #[OA\Response(response: 409, description: 'La demande n\'est pas en attente')]
    public function accept(#[CurrentUser] User $user, #[MapEntity(id: 'id')] LeaveRequest $leave): JsonResponse
    {
        return $this->json(LeaveRequestView::fromEntity($this->leaves->accept($leave), $user));
    }

    #[Route('/api/leaves/{id}/refuse', name: 'api_leaves_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(LeaveRequestVoter::PARTY, subject: 'leave', statusCode: Response::HTTP_NOT_FOUND)]
    #[IsGranted(LeaveRequestVoter::DECIDE, subject: 'leave', statusCode: Response::HTTP_FORBIDDEN)]
    #[OA\Response(response: 200, description: 'Demande refusée', content: new Model(type: LeaveRequestView::class))]
    #[OA\Response(response: 400, description: 'Motif invalide')]
    #[OA\Response(response: 403, description: 'Le demandeur ne décide pas de sa propre demande')]
    #[OA\Response(response: 404, description: 'Demande inexistante ou hors de l\'équipe')]
    #[OA\Response(response: 409, description: 'La demande n\'est pas en attente')]
    public function refuse(
        #[CurrentUser] User $user,
        #[MapEntity(id: 'id')] LeaveRequest $leave,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] RejectionInput $input,
    ): JsonResponse {
        \assert(null !== $input->reason);

        return $this->json(LeaveRequestView::fromEntity($this->leaves->refuse($leave, $input->reason), $user));
    }

    /**
     * @param list<LeaveRequest> $leaves
     */
    private function list(array $leaves, User $viewer): JsonResponse
    {
        return $this->json(array_map(static fn (LeaveRequest $leave): LeaveRequestView => LeaveRequestView::fromEntity($leave, $viewer), $leaves));
    }
}
