<?php

namespace App\Tests\Controller\Leave;

use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;

/**
 * GET /api/team/leaves (manager et plus, 403 sinon) : demandes en attente des personnes encadrées directement,
 * date de début croissante — spec 007 (C6, C17). Liste sans paramètre : aucun cas de validation (400).
 */
final class TeamLeavesTest extends LeaveTestCase
{
    public function testC6LeManagerVoitLesDemandesEnAttenteDeSonEquipeEtSeulementCelles(): void
    {
        $team = $this->createTeam();
        $sophieLater = (int) $this->createLeave($team['sophie'], $this->day(20), $this->day(22))->getId();
        $tomSoon = (int) $this->createLeave($team['tom'], $this->day(2), $this->day(3))->getId();
        $sophieSoon = (int) $this->createLeave($team['sophie'], $this->day(8), $this->day(8))->getId();
        // Exclues : autres états de l'équipe, autre équipe, ses propres demandes
        foreach ([LeaveStatus::Accepted, LeaveStatus::Refused, LeaveStatus::Withdrawn, LeaveStatus::Cancelled] as $i => $status) {
            $this->createLeaveInStatus($team['tom'], $this->day(30 + 3 * $i), $this->day(31 + 3 * $i), $status, $team['admin']);
        }
        $this->createLeave($team['ines'], $this->day(4), $this->day(5));
        $this->createLeave($team['marc'], $this->day(6), $this->day(7));
        $this->login($team['marc']);

        $this->client->request('GET', '/api/team/leaves');

        $list = $this->leaveList();
        self::assertSame([$tomSoon, $sophieSoon, $sophieLater], $this->ids($list));
        $byId = array_column($list, null, 'id');
        self::assertSame(['id' => $team['tom']->getId(), 'name' => 'Tom Salarié'], $byId[$tomSoon]['requester']);
        self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $byId[$sophieLater]['requester']);
        self::assertSame($this->day(20), $byId[$sophieLater]['startDate']);
        self::assertSame($this->day(22), $byId[$sophieLater]['endDate']);
        foreach ($list as $view) {
            self::assertSame('pending', $view['status']);
            self::assertTrue($view['decidable'], 'Le responsable peut décider la demande.');
            self::assertFalse($view['withdrawable']);
            self::assertFalse($view['cancellable']);
        }
    }

    public function testC6LAdminVoitLesDemandesEnAttenteDesManagersQuIlEncadre(): void
    {
        $team = $this->createTeam();
        $marc = (int) $this->createLeave($team['marc'], $this->day(9), $this->day(10))->getId();
        $julie = (int) $this->createLeave($team['julie'], $this->day(1), $this->day(2))->getId();
        // Pas encadrée directement par l'admin
        $this->createLeave($team['sophie'], $this->day(3), $this->day(4));
        $this->login($team['admin']);

        $this->client->request('GET', '/api/team/leaves');

        $list = $this->leaveList();
        self::assertSame([$julie, $marc], $this->ids($list));
        foreach ($list as $view) {
            self::assertTrue($view['decidable']);
            self::assertTrue($view['cancellable'], 'L\'admin peut annuler une demande en attente.');
        }
    }

    public function testC6UnManagerSansDemandeATraiterRecoitUneListeVide(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['sophie'], $this->day(3), $this->day(4));
        $this->login($team['julie']);

        $this->client->request('GET', '/api/team/leaves');

        self::assertSame([], $this->leaveList());
    }

    public function testC17LesMotifsDesDemandesNonEnAttenteDeLEquipeNApparaissentPas(): void
    {
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(11), LeaveStatus::Refused);
        $this->createLeaveInStatus($team['sophie'], $this->day(12), $this->day(13), LeaveStatus::Cancelled, $team['admin']);
        $this->login($team['marc']);

        $this->client->request('GET', '/api/team/leaves');

        self::assertSame([], $this->leaveList());
        self::assertStringNotContainsString('secret', (string) $this->client->getResponse()->getContent());
    }

    public function testAutorisationUnSalarieRecoit403(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['tom'], $this->day(3), $this->day(4));
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/team/leaves');

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString($this->day(3), (string) $this->client->getResponse()->getContent());
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['tom'], $this->day(3), $this->day(4));

        $this->client->request('GET', '/api/team/leaves');

        $this->assertErrorResponse(401);
    }
}
