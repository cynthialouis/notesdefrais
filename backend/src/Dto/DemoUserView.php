<?php

namespace App\Dto;

use App\Enum\UserRole;

/**
 * Compte de démo proposé sur la page de connexion (sans email).
 */
final readonly class DemoUserView
{
    public function __construct(
        public int $id,
        public string $name,
        public UserRole $role,
    ) {
    }
}
