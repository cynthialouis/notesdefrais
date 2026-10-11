<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    /**
     * La requête est traitée par DemoLoginAuthenticator, qui répond avant d'atteindre ce contrôleur.
     * La route doit exister pour que le routeur (qui passe avant le firewall) ne réponde pas 404.
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): Response
    {
        throw new \LogicException('Cette route est interceptée par DemoLoginAuthenticator.');
    }
}
