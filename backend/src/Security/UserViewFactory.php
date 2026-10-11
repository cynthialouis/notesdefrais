<?php

namespace App\Security;

use App\Dto\UserView;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Construit la vue de l'utilisateur courant. Les rôles accessibles viennent de la hiérarchie
 * déclarée dans security.yaml : le front n'a pas à la recalculer.
 */
final class UserViewFactory
{
    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    public function create(User $user): UserView
    {
        $accessibleRoles = [];
        foreach ($this->roleHierarchy->getReachableRoleNames($user->getRoles()) as $securityRole) {
            $role = UserRole::fromSecurityRole($securityRole);
            if (null !== $role) {
                $accessibleRoles[] = $role;
            }
        }

        return new UserView(
            (int) $user->getId(),
            $user->getName(),
            $user->getEmail(),
            $user->getRole(),
            array_values(array_unique($accessibleRoles, \SORT_REGULAR)),
        );
    }
}
