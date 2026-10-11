<?php

namespace App\Dto;

/**
 * Compte de démo proposé sur la page de connexion (sans email).
 */
final readonly class DemoUserView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
    ) {
    }
}
