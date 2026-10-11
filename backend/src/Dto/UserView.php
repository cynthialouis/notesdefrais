<?php

namespace App\Dto;

use App\Enum\UserRole;

/**
 * Utilisateur courant, tel que renvoyé au front (connexion et GET /api/me).
 */
final readonly class UserView
{
    /**
     * @param list<UserRole> $accessibleRoles rôles dont l'utilisateur a les droits, selon la hiérarchie
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public UserRole $role,
        public array $accessibleRoles,
    ) {
    }
}
