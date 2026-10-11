<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Comptes de démo : 1 admin, 2 managers, 4 salariés (2 par manager).
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->user($manager, 'Alice Martin', 'alice.martin@demo.test', UserRole::Admin);

        $claire = $this->user($manager, 'Claire Dubois', 'claire.dubois@demo.test', UserRole::Manager);
        $hugo = $this->user($manager, 'Hugo Lefèvre', 'hugo.lefevre@demo.test', UserRole::Manager);

        $this->user($manager, 'Léa Bernard', 'lea.bernard@demo.test', UserRole::Employee, $claire);
        $this->user($manager, 'Tom Petit', 'tom.petit@demo.test', UserRole::Employee, $claire);
        $this->user($manager, 'Inès Moreau', 'ines.moreau@demo.test', UserRole::Employee, $hugo);
        $this->user($manager, 'Noah Laurent', 'noah.laurent@demo.test', UserRole::Employee, $hugo);

        $manager->flush();
    }

    private function user(ObjectManager $manager, string $name, string $email, UserRole $role, ?User $teamManager = null): User
    {
        $user = (new User())
            ->setName($name)
            ->setEmail($email)
            ->setRole($role)
            ->setManager($teamManager);
        $manager->persist($user);

        return $user;
    }
}
