<?php

namespace App\Controller;

use App\Dto\UserView;
use App\Entity\User;
use App\Security\UserViewFactory;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Utilisateur courant et rôles accessibles', content: new Model(type: UserView::class))]
    #[OA\Response(response: 401, description: 'Non connecté')]
    public function __invoke(#[CurrentUser] User $user, UserViewFactory $userViews): JsonResponse
    {
        return $this->json($userViews->create($user));
    }
}
