<?php

namespace App\Tests\Controller\Leave;

use App\Entity\LeaveRequest;
use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/leaves/{id}/refuse, corps { reason } (responsable du demandeur ; 403 pour le demandeur, 404 pour tout autre)
 * — spec 007 (C8, C9, C10, C17). Motif : 5 à 1000 caractères après retrait des blancs autour (RejectionInput, research D3).
 */
final class RefuseLeaveTest extends LeaveTestCase
{
    public function testC8LeManagerRefuseAvecUnMotifEtEnregistreQuiEtQuand(): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Période de clôture']);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertLeaveView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('refused', $view['status']);
        self::assertSame('Période de clôture', $view['refusalReason']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['decidedBy']);
        $this->assertIsNow($view['decidedAt']);
        self::assertNull($view['cancelledAt']);
        self::assertNull($view['cancelledBy']);
        self::assertNull($view['cancellationReason']);
        self::assertFalse($view['decidable']);
        self::assertFalse($view['withdrawable']);
        self::assertFalse($view['cancellable']);

        $stored = $this->findLeave($id);
        self::assertInstanceOf(LeaveRequest::class, $stored);
        self::assertSame(LeaveStatus::Refused, $stored->getStatus());
        self::assertSame('Période de clôture', $stored->getRefusalReason());
        self::assertSame($team['marc']->getId(), $stored->getDecidedBy()?->getId());
        self::assertSame($this->nowTimestamp(), $stored->getDecidedAt()?->getTimestamp());
    }

    public function testC8LAdminRefuseLaDemandeDUnManagerQuiLuiEstRattache(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['julie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Séminaire de direction']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('refused', $this->responseJson()['status']);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Refused, $stored?->getStatus());
        self::assertSame('Séminaire de direction', $stored?->getRefusalReason());
        self::assertSame($team['admin']->getId(), $stored?->getDecidedBy()?->getId());
    }

    public function testC8LeDemandeurVoitLeMotifDuRefusDansSesDemandes(): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Période de clôture']);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/me/leaves');

        $list = $this->leaveList();
        self::assertSame([$id], $this->ids($list));
        self::assertSame('refused', $list[0]['status']);
        self::assertSame('Période de clôture', $list[0]['refusalReason']);
    }

    public function testCasLimiteLeMotifEstConserveSansLesEspacesAutour(): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => "  \u{00A0}Période de clôture\u{3000} \n"]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Période de clôture', $this->responseJson()['refusalReason']);
        self::assertSame('Période de clôture', $this->findLeave($id)?->getRefusalReason());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedReasons(): iterable
    {
        yield '5 caractères exactement' => ['abcde'];
        yield '5 caractères accentués' => ['éèàùç'];
        yield '5 caractères entourés d\'espaces' => ['   abcde   '];
        yield '1000 caractères' => [str_repeat('a', 1000)];
    }

    #[DataProvider('acceptedReasons')]
    public function testC9MotifAccepte(string $reason): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => $reason]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(trim($reason), $this->responseJson()['refusalReason']);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Refused, $stored?->getStatus());
        self::assertSame(trim($reason), $stored?->getRefusalReason());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'motif absent' => [[]];
        yield 'motif null' => [['reason' => null]];
        yield 'motif vide' => [['reason' => '']];
        yield 'motif fait d\'espaces' => [['reason' => '          ']];
        yield 'motif fait d\'espaces Unicode' => [['reason' => "\u{00A0}\u{00A0}\u{3000}\u{2028}\u{FEFF}\u{00A0}"]];
        yield 'motif de 4 caractères' => [['reason' => 'abcd']];
        yield 'motif de 4 caractères entourés d\'espaces' => [['reason' => '   abcd   ']];
        yield 'motif de 1001 caractères' => [['reason' => str_repeat('a', 1001)]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidBodies')]
    public function testC9MotifInvalideRenvoie400SurLeChampReasonSansRefuser(array $body): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', $body);

        $this->assertValidationError('reason');
        $this->assertStillPending($id);
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
    public function testC10RefuserUneDemandeQuiNEstPasEnAttenteRenvoie409SansChangement(string $status): void
    {
        $team = $this->createTeam();
        $leave = $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::from($status), $team['admin']);
        $id = (int) $leave->getId();
        $reasonBefore = $this->findLeave($id)?->getRefusalReason();
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Motif valable']);

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::from($status), $stored?->getStatus());
        self::assertSame($reasonBefore, $stored?->getRefusalReason());
    }

    public function testC10UnSecondRefusEstRefuseEtLePremierMotifConserve(): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Premier motif']);
        self::assertResponseStatusCodeSame(200);

        self::mockTime(new \DateTimeImmutable('2026-11-10 13:00:00', new \DateTimeZone('Europe/Paris')));
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Second motif']);

        $this->assertErrorResponse(409);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Refused, $stored?->getStatus());
        self::assertSame('Premier motif', $stored?->getRefusalReason());
        self::assertSame($this->nowTimestamp(), $stored?->getDecidedAt()?->getTimestamp());
    }

    public function testC10UneAcceptationApresUnRefusEstRefusee(): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Premier motif']);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', '/api/leaves/'.$id.'/accept');

        $this->assertErrorResponse(409);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Refused, $stored?->getStatus());
        self::assertSame('Premier motif', $stored?->getRefusalReason());
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
    public function testAutorisationLeDemandeurNeRefusePasSaPropreDemande403(string $requester): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team[$requester], $this->day(10), $this->day(14))->getId();
        $this->login($team[$requester]);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Je me refuse']);

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertStillPending($id);
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
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team[$reader]);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Motif valable']);

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString($this->day(10), (string) $this->client->getResponse()->getContent());
        $this->assertStillPending($id);
    }

    public function testAutorisationDemandeInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/leaves/'.($id + 1000).'/refuse', ['reason' => 'Motif valable']);

        $this->assertErrorResponse(404);
        $this->assertStillPending($id);
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = $this->pendingLeaveOfSophie($team);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/refuse', ['reason' => 'Motif valable']);

        $this->assertErrorResponse(401);
        $this->assertStillPending($id);
    }

    /**
     * @param array<string, \App\Entity\User> $team
     */
    private function pendingLeaveOfSophie(array $team): int
    {
        return (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
    }

    private function assertStillPending(int $id): void
    {
        $stored = $this->findLeave($id);
        self::assertInstanceOf(LeaveRequest::class, $stored);
        self::assertSame(LeaveStatus::Pending, $stored->getStatus());
        self::assertNull($stored->getRefusalReason());
        self::assertNull($stored->getDecidedBy());
        self::assertNull($stored->getDecidedAt());
    }
}
