<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/auth/login : le compte de démo choisi.
 */
final readonly class LoginInput
{
    public function __construct(
        #[Assert\NotNull(message: 'Choisissez un compte.')]
        #[Assert\Positive(message: 'Identifiant de compte invalide.')]
        public ?int $userId = null,
    ) {
    }
}
