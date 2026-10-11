<?php

namespace App\Enum;

enum UserRole: string
{
    case Employee = 'employee';
    case Manager = 'manager';
    case Admin = 'admin';

    /**
     * Rôle Symfony correspondant, utilisé par la sécurité et la hiérarchie des rôles.
     */
    public function securityRole(): string
    {
        return 'ROLE_'.strtoupper($this->name);
    }

    public static function fromSecurityRole(string $securityRole): ?self
    {
        foreach (self::cases() as $role) {
            if ($role->securityRole() === $securityRole) {
                return $role;
            }
        }

        return null;
    }
}
