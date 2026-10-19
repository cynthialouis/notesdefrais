<?php

namespace App\Workflow;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Auteur d'une transition : l'utilisateur connecté. Une transition sans utilisateur connecté
 * est une erreur de programmation (les données de démo écrivent leur historique elles-mêmes).
 */
final class CurrentActor
{
    public function __construct(private readonly Security $security)
    {
    }

    public function get(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Une transition de note ou de demande de congé exige un utilisateur connecté.');
        }

        return $user;
    }
}
