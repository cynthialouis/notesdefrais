<?php

namespace App\Dto;

use App\Entity\User;

/**
 * Référence courte à une personne (auteur d'une note, d'une décision, d'une action).
 */
final readonly class PersonRef
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self((int) $user->getId(), $user->getName());
    }
}
