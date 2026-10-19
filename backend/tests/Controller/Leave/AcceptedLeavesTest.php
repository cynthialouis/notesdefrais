<?php

namespace App\Tests\Controller\Leave;

use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * GET /api/leaves (connecté) : congés acceptés en cours ou à venir de tout le monde (date de fin pas antérieure
 * à aujourd'hui, heure de Paris), date de début croissante — spec 007 (C15, C17).
 * Liste sans paramètre : aucun cas de validation (400).
 */
final class AcceptedLeavesTest extends LeaveTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function readers(): iterable
    {
        yield 'salariée d\'une autre équipe' => ['ines'];
        yield 'collègue salarié' => ['tom'];
        yield 'manager' => ['julie'];
        yield 'admin' => ['admin'];
    }

    #[DataProvider('readers')]
    public function testC15ToutUtilisateurConnecteVoitLesCongesAcceptesEnCoursOuAVenirDeTousTriesParDebut(string $reader): void
    {
        $team = $this->createTeam();
        $sophieLater = (int) $this->createLeaveInStatus($team['sophie'], $this->day(20), $this->day(24), LeaveStatus::Accepted)->getId();
        $inesSoon = (int) $this->createLeaveInStatus($team['ines'], $this->day(3), $this->day(4), LeaveStatus::Accepted)->getId();
        $marcOngoing = (int) $this->createLeaveInStatus($team['marc'], $this->day(-2), $this->day(2), LeaveStatus::Accepted)->getId();
        $endsToday = (int) $this->createLeaveInStatus($team['tom'], $this->day(-5), self::TODAY, LeaveStatus::Accepted)->getId();
        // Terminés : absents
        $this->createLeaveInStatus($team['tom'], $this->day(-10), $this->day(-1), LeaveStatus::Accepted);
        $this->createLeaveInStatus($team['julie'], $this->day(-40), $this->day(-30), LeaveStatus::Accepted);
        $this->login($team[$reader]);

        $this->client->request('GET', '/api/leaves');

        $list = $this->leaveList();
        self::assertSame([$endsToday, $marcOngoing, $inesSoon, $sophieLater], $this->ids($list));
        $byId = array_column($list, null, 'id');
        self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $byId[$sophieLater]['requester']);
        self::assertSame($this->day(20), $byId[$sophieLater]['startDate']);
        self::assertSame($this->day(24), $byId[$sophieLater]['endDate']);
        self::assertSame(['id' => $team['ines']->getId(), 'name' => 'Inès Salariée'], $byId[$inesSoon]['requester']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $byId[$marcOngoing]['requester']);
        self::assertSame(self::TODAY, $byId[$endsToday]['endDate']);
        foreach ($list as $view) {
            self::assertSame('accepted', $view['status']);
            self::assertNull($view['refusalReason']);
            self::assertNull($view['cancellationReason']);
            self::assertFalse($view['decidable']);
            self::assertFalse($view['withdrawable']);
        }
    }

    public function testC15LeLecteurNonAdminNePeutAnnulerAucunCongeEtLAdminLesPeutTous(): void
    {
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['sophie'], $this->day(3), $this->day(4), LeaveStatus::Accepted);
        $this->createLeaveInStatus($team['marc'], $this->day(5), $this->day(6), LeaveStatus::Accepted);

        $this->login($team['tom']);
        $this->client->request('GET', '/api/leaves');
        $list = $this->leaveList();
        self::assertCount(2, $list);
        foreach ($list as $view) {
            self::assertFalse($view['cancellable']);
        }

        $this->login($team['admin']);
        $this->client->request('GET', '/api/leaves');
        $list = $this->leaveList();
        self::assertCount(2, $list);
        foreach ($list as $view) {
            self::assertTrue($view['cancellable']);
        }
    }

    public function testC15LeDemandeurVoitAussiSonPropreCongeAccepte(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(3), $this->day(4), LeaveStatus::Accepted)->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/leaves');

        self::assertSame([$id], $this->ids($this->leaveList()));
    }

    /**
     * À 00 h 30 le 10/11 à Paris, il est encore le 09/11 en UTC : un congé terminé le 09/11 est terminé (heure de Paris).
     */
    public function testC15AujourdhuiSEntendEnHeureDeParis(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-11-10 00:30:00', new \DateTimeZone('Europe/Paris')));
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['sophie'], '2026-11-05', '2026-11-09', LeaveStatus::Accepted);
        $endsToday = (int) $this->createLeaveInStatus($team['tom'], '2026-11-06', '2026-11-10', LeaveStatus::Accepted)->getId();
        $this->login($team['ines']);

        $this->client->request('GET', '/api/leaves');

        self::assertSame([$endsToday], $this->ids($this->leaveList()));
    }

    public function testC15SansCongeAVenirLaListeEstVide(): void
    {
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['sophie'], $this->day(-10), $this->day(-1), LeaveStatus::Accepted);
        $this->createLeave($team['tom'], $this->day(3), $this->day(4));
        $this->login($team['ines']);

        $this->client->request('GET', '/api/leaves');

        self::assertSame([], $this->leaveList());
    }

    public function testC13C15UnCongeAnnuleDisparaitDeLaListe(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(3), $this->day(4), LeaveStatus::Accepted)->getId();
        $this->login($team['admin']);
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Erreur de saisie']);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['tom']);
        $this->client->request('GET', '/api/leaves');

        self::assertSame([], $this->leaveList());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hiddenStatuses(): iterable
    {
        yield 'en attente' => ['pending'];
        yield 'refusée' => ['refused'];
        yield 'retirée' => ['withdrawn'];
        yield 'annulée' => ['cancelled'];
    }

    /**
     * Une demande non acceptée de Sophie n'apparaît dans aucune liste d'une autre personne, sauf une demande
     * en attente pour son responsable (Marc, /team/leaves) et pour l'admin (/leaves/pending) ; ses motifs non plus.
     */
    #[DataProvider('hiddenStatuses')]
    public function testC17UneDemandeNonAccepteeNEstVueQueDeSonDemandeurSaufEnAttentePourSonResponsableEtLAdmin(string $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::from($status), $team['admin'])->getId();
        $isPending = 'pending' === $status;

        // Le demandeur la voit
        $this->login($team['sophie']);
        $this->client->request('GET', '/api/me/leaves');
        self::assertSame([$id], $this->ids($this->leaveList()));

        $routes = ['/api/leaves', '/api/me/leaves', '/api/team/leaves', '/api/leaves/pending'];
        $expectedVisible = [
            'marc' => $isPending ? ['/api/team/leaves'] : [],
            'admin' => $isPending ? ['/api/leaves/pending'] : [],
            'tom' => [],
            'julie' => [],
            'ines' => [],
        ];
        foreach ($expectedVisible as $reader => $visibleIn) {
            $this->login($team[$reader]);
            foreach ($routes as $route) {
                $this->client->request('GET', $route);
                $forbidden = ('/api/team/leaves' === $route && \in_array($reader, ['tom', 'ines'], true))
                    || ('/api/leaves/pending' === $route && 'admin' !== $reader);
                if ($forbidden) {
                    $this->assertErrorResponse(403);
                    continue;
                }
                $ids = $this->ids($this->leaveList());
                if (\in_array($route, $visibleIn, true)) {
                    self::assertContains($id, $ids, "$reader doit voir la demande $status de Sophie dans $route.");
                } else {
                    self::assertNotContains($id, $ids, "$reader ne doit pas voir la demande $status de Sophie dans $route.");
                }
                $body = (string) $this->client->getResponse()->getContent();
                self::assertStringNotContainsString('secret', $body, "Motif de Sophie exposé à $reader par $route.");
            }
        }
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['sophie'], $this->day(3), $this->day(4), LeaveStatus::Accepted);

        $this->client->request('GET', '/api/leaves');

        $this->assertErrorResponse(401);
        self::assertStringNotContainsString($this->day(3), (string) $this->client->getResponse()->getContent());
    }
}
