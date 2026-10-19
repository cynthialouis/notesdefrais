<?php

namespace App\Tests\Controller\Leave;

use App\Entity\LeaveRequest;
use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/leaves/{id}/accept (responsable du demandeur ; 403 pour le demandeur, 404 pour tout autre)
 * — spec 007 (C7, C10, C17). Pas de corps : aucun cas de validation (400) sur cette route.
 */
final class AcceptLeaveTest extends LeaveTestCase
{
    public function testC7LeManagerAccepteUneDemandeDeSonEquipeEtEnregistreQuiEtQuand(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertLeaveView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('accepted', $view['status']);
        self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $view['requester']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['decidedBy']);
        $this->assertIsNow($view['decidedAt']);
        self::assertNull($view['refusalReason']);
        self::assertNull($view['cancelledAt']);
        self::assertNull($view['cancelledBy']);
        self::assertNull($view['cancellationReason']);
        self::assertFalse($view['decidable']);
        self::assertFalse($view['withdrawable']);
        self::assertFalse($view['cancellable']);

        $stored = $this->findLeave($id);
        self::assertInstanceOf(LeaveRequest::class, $stored);
        self::assertSame(LeaveStatus::Accepted, $stored->getStatus());
        self::assertSame($team['marc']->getId(), $stored->getDecidedBy()?->getId());
        self::assertSame($this->nowTimestamp(), $stored->getDecidedAt()?->getTimestamp());
        self::assertNull($stored->getRefusalReason());
        self::assertSame($this->day(10), $stored->getStartDate()->format('Y-m-d'));
        self::assertSame($this->day(14), $stored->getEndDate()->format('Y-m-d'));
    }

    public function testC7LAdminAccepteLaDemandeDUnManagerQuiLuiEstRattache(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['marc'], $this->day(10), $this->day(14))->getId();
        $this->login($team['admin']);

        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        self::assertSame('accepted', $view['status']);
        self::assertSame(['id' => $team['admin']->getId(), 'name' => 'Alice Admin'], $view['decidedBy']);
        // Lue par l'admin : un congé accepté reste annulable
        self::assertTrue($view['cancellable']);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Accepted, $stored?->getStatus());
        self::assertSame($team['admin']->getId(), $stored?->getDecidedBy()?->getId());
    }

    public function testC7LeCongeAccepteNeFigurePlusDansLesDemandesATraiter(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/leaves/'.$id.'/accept');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/team/leaves');

        self::assertSame([], $this->leaveList());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notPendingStatuses(): iterable
    {
        yield 'acceptée' => ['accepted'];
        yield 'refusée' => ['refused'];
        yield 'retirée' => ['withdrawn'];
        yield 'annulée' => ['cancelled'];
    }

    #[DataProvider('notPendingStatuses')]
    public function testC10AccepterUneDemandeQuiNEstPasEnAttenteRenvoie409SansChangement(string $status): void
    {
        $team = $this->createTeam();
        $leave = $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::from($status), $team['admin']);
        $id = (int) $leave->getId();
        $before = $this->findLeave($id);
        self::assertNotNull($before);
        $decidedBy = $before->getDecidedBy()?->getId();
        $decidedAt = $before->getDecidedAt()?->getTimestamp();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::from($status), $stored?->getStatus());
        self::assertSame($decidedBy, $stored?->getDecidedBy()?->getId());
        self::assertSame($decidedAt, $stored?->getDecidedAt()?->getTimestamp());
    }

    public function testC10UneSecondeAcceptationEstRefuseeEtLaPremiereConservee(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/leaves/'.$id.'/accept');
        self::assertResponseStatusCodeSame(200);

        self::mockTime(new \DateTimeImmutable('2026-11-10 13:00:00', new \DateTimeZone('Europe/Paris')));
        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        $this->assertErrorResponse(409);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Accepted, $stored?->getStatus());
        self::assertSame($this->nowTimestamp(), $stored?->getDecidedAt()?->getTimestamp(), 'La date de la première décision est conservée.');
        self::assertSame($team['marc']->getId(), $stored?->getDecidedBy()?->getId());
    }

    public function testC10UneDemandeAccepteeNePeutPlusEtreRefusee(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/leaves/'.$id.'/accept');
        self::assertResponseStatusCodeSame(200);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Changement d\'avis']);

        $this->assertErrorResponse(409);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Accepted, $stored?->getStatus());
        self::assertNull($stored?->getRefusalReason());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function selfRequesters(): iterable
    {
        yield 'salariée' => ['sophie'];
        yield 'manager' => ['marc'];
    }

    #[DataProvider('selfRequesters')]
    public function testAutorisationLeDemandeurNAcceptePasSaPropreDemande403(string $requester): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team[$requester], $this->day(10), $this->day(14))->getId();
        $this->login($team[$requester]);

        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Pending, $stored?->getStatus());
        self::assertNull($stored?->getDecidedBy());
        self::assertNull($stored?->getDecidedAt());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function strangers(): iterable
    {
        yield 'collègue salarié' => ['tom'];
        yield 'salariée d\'une autre équipe' => ['ines'];
        yield 'manager d\'une autre équipe' => ['julie'];
        yield 'admin, responsable du manager mais pas du salarié' => ['admin'];
    }

    #[DataProvider('strangers')]
    public function testAutorisationToutAutreQueLeResponsableRecoit404(string $reader): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team[$reader]);

        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString($this->day(10), (string) $this->client->getResponse()->getContent());
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Pending, $stored?->getStatus());
        self::assertNull($stored?->getDecidedBy());
    }

    /**
     * Un congé accepté est visible de tous (C15) : un tiers qui tente de le décider reçoit quand même 404, pas 403
     * ni 409 (research D7).
     */
    public function testAutorisationUnTiersRecoit404MemeSurUnCongeAcceptePourTous(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::Accepted)->getId();
        $this->login($team['ines']);

        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        $this->assertErrorResponse(404);
        self::assertSame(LeaveStatus::Accepted, $this->findLeave($id)?->getStatus());
    }

    public function testAutorisationDemandeInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/leaves/'.($id + 1000).'/accept');

        $this->assertErrorResponse(404);
        self::assertSame(LeaveStatus::Pending, $this->findLeave($id)?->getStatus());
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();

        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        $this->assertErrorResponse(401);
        self::assertSame(LeaveStatus::Pending, $this->findLeave($id)?->getStatus());
    }

    public function testCasLimiteAccepterEnGetEstRefuse(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);

        $this->client->request('GET', '/api/leaves/'.$id.'/accept');

        $this->assertErrorResponse(405);
        self::assertSame(LeaveStatus::Pending, $this->findLeave($id)?->getStatus());
    }
}
