<?php

namespace App\Tests\Controller\Leave;

use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * GET /api/leaves/pending (admin, 403 sinon) : demandes en attente de tout le monde, date de début croissante
 * — spec 007 (C18, C17). Liste sans paramètre : aucun cas de validation (400).
 */
final class PendingLeavesTest extends LeaveTestCase
{
    public function testC18LAdminVoitLesDemandesEnAttenteDeToutesLesEquipesTrieesParDebut(): void
    {
        $team = $this->createTeam();
        $sophie = (int) $this->createLeave($team['sophie'], $this->day(12), $this->day(13))->getId();
        $ines = (int) $this->createLeave($team['ines'], $this->day(2), $this->day(5))->getId();
        $marc = (int) $this->createLeave($team['marc'], $this->day(7), $this->day(7))->getId();
        $tom = (int) $this->createLeave($team['tom'], $this->day(30), $this->day(31))->getId();
        // Exclues : tout ce qui n'est pas en attente
        foreach ([LeaveStatus::Accepted, LeaveStatus::Refused, LeaveStatus::Withdrawn, LeaveStatus::Cancelled] as $i => $status) {
            $this->createLeaveInStatus($team['ines'], $this->day(40 + 3 * $i), $this->day(41 + 3 * $i), $status, $team['admin']);
        }
        $this->login($team['admin']);

        $this->client->request('GET', '/api/leaves/pending');

        $list = $this->leaveList();
        self::assertSame([$ines, $marc, $sophie, $tom], $this->ids($list));
        $byId = array_column($list, null, 'id');
        self::assertSame(['id' => $team['ines']->getId(), 'name' => 'Inès Salariée'], $byId[$ines]['requester']);
        self::assertSame($this->day(2), $byId[$ines]['startDate']);
        self::assertSame($this->day(5), $byId[$ines]['endDate']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $byId[$marc]['requester']);
        foreach ($list as $view) {
            self::assertSame('pending', $view['status']);
            self::assertTrue($view['cancellable'], 'L\'admin peut annuler une demande en attente.');
            self::assertFalse($view['withdrawable']);
        }
        // L'admin ne décide que les demandes des managers qu'il encadre directement
        self::assertTrue($byId[$marc]['decidable']);
        self::assertFalse($byId[$sophie]['decidable']);
        self::assertFalse($byId[$ines]['decidable']);
        self::assertFalse($byId[$tom]['decidable']);
    }

    public function testC18SansDemandeEnAttenteLaListeEstVide(): void
    {
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['sophie'], $this->day(3), $this->day(4), LeaveStatus::Accepted);
        $this->login($team['admin']);

        $this->client->request('GET', '/api/leaves/pending');

        self::assertSame([], $this->leaveList());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonAdmins(): iterable
    {
        yield 'manager' => ['marc'];
        yield 'salariée' => ['sophie'];
    }

    #[DataProvider('nonAdmins')]
    public function testAutorisationUnNonAdminRecoit403(string $reader): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['ines'], $this->day(3), $this->day(4));
        $this->login($team[$reader]);

        $this->client->request('GET', '/api/leaves/pending');

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString($this->day(3), (string) $this->client->getResponse()->getContent());
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['ines'], $this->day(3), $this->day(4));

        $this->client->request('GET', '/api/leaves/pending');

        $this->assertErrorResponse(401);
    }
}
