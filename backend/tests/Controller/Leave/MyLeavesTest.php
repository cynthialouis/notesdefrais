<?php

namespace App\Tests\Controller\Leave;

use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;

/**
 * GET /api/me/leaves (connecté) : toutes ses demandes, quel que soit leur état, date de début décroissante,
 * avec le motif d'un refus ou d'une annulation — spec 007 (C16, C17).
 */
final class MyLeavesTest extends LeaveTestCase
{
    public function testC16ChacunVoitToutesSesDemandesDansTousLesEtatsAvecLesMotifs(): void
    {
        $team = $this->createTeam();
        $pending = (int) $this->createLeave($team['sophie'], $this->day(20), $this->day(21))->getId();
        $accepted = (int) $this->createLeave($team['sophie'], $this->day(30), $this->day(32), LeaveStatus::Accepted, decidedBy: $team['marc'])->getId();
        $refused = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(11), LeaveStatus::Refused, decidedBy: $team['marc'], refusalReason: 'Période de clôture')->getId();
        $withdrawn = (int) $this->createLeave($team['sophie'], $this->day(40), $this->day(41), LeaveStatus::Withdrawn)->getId();
        $cancelled = (int) $this->createLeave($team['sophie'], $this->day(-20), $this->day(-18), LeaveStatus::Cancelled, decidedBy: $team['marc'], cancelledBy: $team['admin'], cancellationReason: 'Erreur de saisie')->getId();
        // Demandes des autres : jamais dans « mes demandes »
        $this->createLeave($team['tom'], $this->day(5), $this->day(6));
        $this->createLeave($team['ines'], $this->day(5), $this->day(6), LeaveStatus::Accepted, decidedBy: $team['julie']);
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/me/leaves');

        $list = $this->leaveList();
        // Date de début décroissante
        self::assertSame([$withdrawn, $accepted, $pending, $refused, $cancelled], $this->ids($list));
        $byId = array_column($list, null, 'id');
        foreach ($list as $view) {
            self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $view['requester']);
        }

        self::assertSame('pending', $byId[$pending]['status']);
        self::assertSame($this->day(20), $byId[$pending]['startDate']);
        self::assertSame($this->day(21), $byId[$pending]['endDate']);

        self::assertSame('accepted', $byId[$accepted]['status']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $byId[$accepted]['decidedBy']);
        self::assertNotNull($byId[$accepted]['decidedAt']);

        self::assertSame('refused', $byId[$refused]['status']);
        self::assertSame('Période de clôture', $byId[$refused]['refusalReason']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $byId[$refused]['decidedBy']);

        self::assertSame('withdrawn', $byId[$withdrawn]['status']);

        self::assertSame('cancelled', $byId[$cancelled]['status']);
        self::assertSame('Erreur de saisie', $byId[$cancelled]['cancellationReason']);
        self::assertSame(['id' => $team['admin']->getId(), 'name' => 'Alice Admin'], $byId[$cancelled]['cancelledBy']);
        self::assertNotNull($byId[$cancelled]['cancelledAt']);
    }

    public function testC16LeDemandeurNePeutRetirerQueSaDemandeEnAttenteEtNeDecideNiNAnnuleRien(): void
    {
        $team = $this->createTeam();
        $pending = (int) $this->createLeave($team['sophie'], $this->day(20), $this->day(21))->getId();
        foreach ([LeaveStatus::Accepted, LeaveStatus::Refused, LeaveStatus::Withdrawn, LeaveStatus::Cancelled] as $i => $status) {
            $this->createLeaveInStatus($team['sophie'], $this->day(30 + 3 * $i), $this->day(31 + 3 * $i), $status, $team['admin']);
        }
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/me/leaves');

        $list = $this->leaveList();
        self::assertCount(5, $list);
        foreach ($list as $view) {
            self::assertSame($view['id'] === $pending, $view['withdrawable'], 'Seule la demande en attente se retire.');
            self::assertFalse($view['decidable']);
            self::assertFalse($view['cancellable']);
        }
    }

    public function testC16UnManagerVoitSesPropresDemandesEtPasCellesDeSonEquipe(): void
    {
        $team = $this->createTeam();
        $own = (int) $this->createLeave($team['marc'], $this->day(3), $this->day(4))->getId();
        $this->createLeave($team['sophie'], $this->day(5), $this->day(6));
        $this->createLeave($team['tom'], $this->day(5), $this->day(6), LeaveStatus::Accepted, decidedBy: $team['marc']);
        $this->login($team['marc']);

        $this->client->request('GET', '/api/me/leaves');

        $list = $this->leaveList();
        self::assertSame([$own], $this->ids($list));
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $list[0]['requester']);
        self::assertTrue($list[0]['withdrawable']);
        self::assertFalse($list[0]['decidable'], 'Un manager ne décide jamais de ses propres demandes.');
    }

    public function testC16SansDemandeLaListeEstVide(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['tom'], $this->day(5), $this->day(6));
        $this->login($team['admin']);

        $this->client->request('GET', '/api/me/leaves');

        self::assertSame([], $this->leaveList());
    }

    public function testC17LesMotifsDesDemandesDesAutresNApparaissentPas(): void
    {
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(11), LeaveStatus::Refused);
        $this->createLeaveInStatus($team['sophie'], $this->day(12), $this->day(13), LeaveStatus::Cancelled, $team['admin']);
        foreach (['tom', 'marc', 'admin', 'ines'] as $reader) {
            $this->login($team[$reader]);

            $this->client->request('GET', '/api/me/leaves');

            self::assertSame([], $this->leaveList(), $reader.' ne doit pas voir les demandes de Sophie.');
            $body = (string) $this->client->getResponse()->getContent();
            self::assertStringNotContainsString('secret', $body);
        }
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['sophie'], $this->day(5), $this->day(6));

        $this->client->request('GET', '/api/me/leaves');

        $this->assertErrorResponse(401);
        self::assertStringNotContainsString($this->day(5), (string) $this->client->getResponse()->getContent());
    }
}
