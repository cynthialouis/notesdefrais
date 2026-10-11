<?php

namespace App\Dto;

/**
 * Utilisateur courant, tel que renvoyé au front (connexion et GET /api/me).
 */
final readonly class UserView
{
    /**
     * @param list<string> $accessibleRoles rôles dont l'utilisateur a les droits, selon la hiérarchie
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public array $accessibleRoles,
    ) {
    }
}
