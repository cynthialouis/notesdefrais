<?php

namespace App\Tests\Controller\Leave;

use App\Entity\LeaveRequest;
use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/leaves/{id}/withdraw (son demandeur, 404 pour tout autre) — spec 007 (C11, C12, C17).
 * Pas de corps : aucun cas de validation (400) sur cette route.
 */
final class WithdrawLeaveTest extends LeaveTestCase
{
    public function testC11LeDemandeurRetireSaDemandeEnAttente(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/leaves/'.$id.'/withdraw');

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertLeaveView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('withdrawn', $view['status']);
        self::assertSame($this->day(10), $view['startDate']);
        self::assertSame($this->day(14), $view['endDate']);
        self::assertFalse($view['withdrawable']);
        self::assertFalse($view['decidable']);
        self::assertFalse($view['cancellable']);
        foreach (['decidedAt', 'decidedBy', 'refusalReason', 'cancelledAt', 'cancelledBy', 'cancellationReason'] as $key) {
            self::assertNull($view[$key], $key.' doit rester null.');
        }

        $stored = $this->findLeave($id);
        self::assertInstanceOf(LeaveRequest::class, $stored);
        self::assertSame(LeaveStatus::Withdrawn, $stored->getStatus());
        self::assertNull($stored->getDecidedBy());
        self::assertNull($stored->getCancelledBy());
        self::assertSame(1, $this->countLeaves(), 'Retirer ne supprime pas la demande.');
    }

    public function testC11UnManagerRetireSaPropreDemandeEnAttente(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['marc'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/leaves/'.$id.'/withdraw');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('withdrawn', $this->responseJson()['status']);
        self::assertSame(LeaveStatus::Withdrawn, $this->findLeave($id)?->getStatus());
    }

    public function testC11LaDemandeRetireeNeFigurePlusDansLesDemandesATraiter(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['sophie']);
        $this->client->request('POST', '/api/leaves/'.$id.'/withdraw');
        self::assertResponseStatusCodeSame(200);

        $this->login($team['marc']);
        $this->client->request('GET', '/api/team/leaves');
        self::assertSame([], $this->leaveList());

        $this->login($team['admin']);
        $this->client->request('GET', '/api/leaves/pending');
        self::assertSame([], $this->leaveList());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notPendingStatuses(): iterable
    {
        yield 'acceptée' => ['accepted'];
        yield 'refusée' => ['refused'];
        yield 'déjà retirée' => ['withdrawn'];
        yield 'annulée' => ['cancelled'];
    }

    #[DataProvider('notPendingStatuses')]
    public function testC12RetirerUneDemandeQuiNEstPlusEnAttenteRenvoie409SansChangement(string $status): void
    {
        $team = $this->createTeam();
        $leave = $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::from($status), $team['admin']);
        $id = (int) $leave->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/leaves/'.$id.'/withdraw');

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        self::assertSame(LeaveStatus::from($status), $this->findLeave($id)?->getStatus());
    }

    public function testC12UneDemandeAccepteeEntreTempsNeSeRetirePlus(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/leaves/'.$id.'/accept');
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('POST', '/api/leaves/'.$id.'/withdraw');

        $this->assertErrorResponse(409);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Accepted, $stored?->getStatus());
        self::assertSame($team['marc']->getId(), $stored?->getDecidedBy()?->getId());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function strangers(): iterable
    {
        yield 'son responsable' => ['marc'];
        yield 'collègue salarié' => ['tom'];
        yield 'manager d\'une autre équipe' => ['julie'];
        yield 'admin' => ['admin'];
    }

    #[DataProvider('strangers')]
    public function testAutorisationRetirerLaDemandeDUnAutreRenvoie404(string $reader): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team[$reader]);

        $this->client->request('POST', '/api/leaves/'.$id.'/withdraw');

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString($this->day(10), (string) $this->client->getResponse()->getContent());
        self::assertSame(LeaveStatus::Pending, $this->findLeave($id)?->getStatus());
    }

    public function testAutorisationDemandeInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/leaves/'.($id + 1000).'/withdraw');

        $this->assertErrorResponse(404);
        self::assertSame(LeaveStatus::Pending, $this->findLeave($id)?->getStatus());
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();

        $this->client->request('POST', '/api/leaves/'.$id.'/withdraw');

        $this->assertErrorResponse(401);
        self::assertSame(LeaveStatus::Pending, $this->findLeave($id)?->getStatus());
    }

    public function testCasLimiteRetirerEnGetEstRefuse(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/leaves/'.$id.'/withdraw');

        $this->assertErrorResponse(405);
        self::assertSame(LeaveStatus::Pending, $this->findLeave($id)?->getStatus());
    }
}
