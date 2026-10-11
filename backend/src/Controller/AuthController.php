<?php

namespace App\Controller;

use App\Dto\DemoUserView;
use App\Dto\UserView;
use App\Repository\UserRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    #[Route('/demo-users', name: 'api_auth_demo_users', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Comptes de démo proposés sur la page de connexion', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: DemoUserView::class))))]
    public function demoUsers(UserRepository $users): JsonResponse
    {
        $views = [];
        foreach ($users->findAllOrdered() as $user) {
            $views[] = new DemoUserView((int) $user->getId(), $user->getName(), $user->getRole());
        }

        return $this->json($views);
    }

    /**
     * La requête est traitée par DemoLoginAuthenticator, qui répond avant d'atteindre ce contrôleur.
     * La route doit exister pour que le routeur (qui passe avant le firewall) ne réponde pas 404.
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['userId'], properties: [new OA\Property(property: 'userId', type: 'integer')]))]
    #[OA\Response(response: 200, description: 'Session ouverte : utilisateur courant', content: new Model(type: UserView::class))]
    #[OA\Response(response: 400, description: 'Corps invalide')]
    #[OA\Response(response: 401, description: 'Compte inconnu')]
    public function login(): Response
    {
        throw new \LogicException('Cette route est interceptée par DemoLoginAuthenticator.');
    }
}
