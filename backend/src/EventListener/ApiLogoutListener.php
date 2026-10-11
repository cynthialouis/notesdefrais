<?php

namespace App\EventListener;

use App\Http\ApiError;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Déconnexion de l'API : 204 au lieu d'une redirection, 401 sans session ouverte.
 * (La déconnexion passe avant le contrôle d'accès du firewall : access_control ne suffit pas.)
 */
#[AsEventListener(event: LogoutEvent::class)]
final class ApiLogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(null === $event->getToken()
            ? ApiError::response(Response::HTTP_UNAUTHORIZED)
            : new Response(null, Response::HTTP_NO_CONTENT));
    }
}
