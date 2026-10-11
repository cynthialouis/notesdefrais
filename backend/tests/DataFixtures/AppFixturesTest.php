<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Comptes de démo décrits dans le besoin de la spec 001 : 1 admin, 2 managers, 4 salariés rattachés chacun à un manager.
 */
final class AppFixturesTest extends KernelTestCase
{
    public function testLesComptesDeDemoCorrespondentALaSpec(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new AppFixtures())->load($em);

        $users = $em->getRepository(User::class)->findAll();
        $byRole = [];
        foreach ($users as $user) {
            $byRole[$user->getRole()->value][] = $user;
        }

        self::assertCount(1, $byRole['admin'] ?? []);
        self::assertCount(2, $byRole['manager'] ?? []);
        self::assertCount(4, $byRole['employee'] ?? []);

        foreach ($byRole['employee'] as $employee) {
            self::assertSame(UserRole::Manager, $employee->getManager()?->getRole(), $employee->getName().' doit être rattaché à un manager.');
        }
        foreach ([...$byRole['admin'], ...$byRole['manager']] as $user) {
            self::assertNull($user->getManager());
        }
    }
}
