<?php

namespace App\Tests\Controller\Leave;

use App\Entity\LeaveRequest;
use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/leaves/{id}/cancel, corps { reason } (admin, 403 sinon) : annule une demande en attente ou un congé
 * accepté, quelle que soit sa date — spec 007 (C9, C13, C14). Motif : RejectionInput (research D3).
 */
final class CancelLeaveTest extends LeaveTestCase
{
    public function testC13LAdminAnnuleUnCongeAccepteAvecUnMotifEtEnregistreQuiEtQuand(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::Accepted)->getId();
        $decidedAt = $this->findLeave($id)?->getDecidedAt()?->getTimestamp();
        self::assertNotNull($decidedAt);
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Erreur de saisie']);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertLeaveView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('cancelled', $view['status']);
        self::assertSame('Erreur de saisie', $view['cancellationReason']);
        self::assertSame(['id' => $team['admin']->getId(), 'name' => 'Alice Admin'], $view['cancelledBy']);
        $this->assertIsNow($view['cancelledAt']);
        // La décision d'origine est conservée
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['decidedBy']);
        self::assertNull($view['refusalReason']);
        self::assertFalse($view['cancellable']);
        self::assertFalse($view['decidable']);
        self::assertFalse($view['withdrawable']);

        $stored = $this->findLeave($id);
        self::assertInstanceOf(LeaveRequest::class, $stored);
        self::assertSame(LeaveStatus::Cancelled, $stored->getStatus());
        self::assertSame('Erreur de saisie', $stored->getCancellationReason());
        self::assertSame($team['admin']->getId(), $stored->getCancelledBy()?->getId());
        self::assertSame($this->nowTimestamp(), $stored->getCancelledAt()?->getTimestamp());
        self::assertSame($team['marc']->getId(), $stored->getDecidedBy()?->getId());
        self::assertSame($decidedAt, $stored->getDecidedAt()?->getTimestamp());
    }

    public function testC13LAdminAnnuleUneDemandeEnAttente(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['ines'], $this->day(10), $this->day(14))->getId();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Doublon de demande']);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        self::assertSame('cancelled', $view['status']);
        self::assertSame('Doublon de demande', $view['cancellationReason']);
        self::assertSame(['id' => $team['admin']->getId(), 'name' => 'Alice Admin'], $view['cancelledBy']);
        self::assertNull($view['decidedBy']);
        self::assertNull($view['decidedAt']);

        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Cancelled, $stored?->getStatus());
        self::assertSame('Doublon de demande', $stored?->getCancellationReason());
        self::assertSame($team['admin']->getId(), $stored?->getCancelledBy()?->getId());
        self::assertNull($stored?->getDecidedBy());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function anyDate(): iterable
    {
        yield 'congé terminé' => [-30, -25];
        yield 'congé en cours' => [-2, 2];
        yield 'congé à venir' => [60, 65];
    }

    #[DataProvider('anyDate')]
    public function testC13LAnnulationEstPossibleQuelleQueSoitLaDateDuConge(int $start, int $end): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['tom'], $this->day($start), $this->day($end), LeaveStatus::Accepted)->getId();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Erreur de saisie']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('cancelled', $this->responseJson()['status']);
        self::assertSame(LeaveStatus::Cancelled, $this->findLeave($id)?->getStatus());
    }

    public function testC13LeDemandeurVoitLeMotifDeLAnnulationDansSesDemandes(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::Accepted)->getId();
        $this->login($team['admin']);
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Erreur de saisie']);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/me/leaves');

        $list = $this->leaveList();
        self::assertSame([$id], $this->ids($list));
        self::assertSame('cancelled', $list[0]['status']);
        self::assertSame('Erreur de saisie', $list[0]['cancellationReason']);
    }

    public function testCasLimiteLeMotifEstConserveSansLesEspacesAutour(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => "\u{00A0}  Erreur de saisie \n"]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('Erreur de saisie', $this->responseJson()['cancellationReason']);
        self::assertSame('Erreur de saisie', $this->findLeave($id)?->getCancellationReason());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedReasons(): iterable
    {
        yield '5 caractères exactement' => ['abcde'];
        yield '5 caractères entourés d\'espaces' => ['   abcde   '];
        yield '1000 caractères' => [str_repeat('a', 1000)];
    }

    #[DataProvider('acceptedReasons')]
    public function testC9MotifDAnnulationAccepte(string $reason): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => $reason]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(trim($reason), $this->responseJson()['cancellationReason']);
        self::assertSame(trim($reason), $this->findLeave($id)?->getCancellationReason());
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
        yield 'motif fait d\'espaces Unicode' => [['reason' => "\u{00A0}\u{3000}\u{00A0}"]];
        yield 'motif de 4 caractères' => [['reason' => 'abcd']];
        yield 'motif de 4 caractères entourés d\'espaces' => [['reason' => '   abcd   ']];
        yield 'motif de 1001 caractères' => [['reason' => str_repeat('a', 1001)]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidBodies')]
    public function testC9MotifDAnnulationInvalideRenvoie400SurLeChampReasonSansAnnuler(array $body): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::Accepted)->getId();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', $body);

        $this->assertValidationError('reason');
        $this->assertNotCancelled($id, LeaveStatus::Accepted);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function finalStatuses(): iterable
    {
        yield 'refusée' => ['refused'];
        yield 'retirée' => ['withdrawn'];
    }

    #[DataProvider('finalStatuses')]
    public function testC14AnnulerUneDemandeRefuseeOuRetireeRenvoie409SansChangement(string $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::from($status))->getId();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Motif valable']);

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertNotCancelled($id, LeaveStatus::from($status));
    }

    public function testC14AnnulerUneDemandeDejaAnnuleeRenvoie409EtConserveLaPremiereAnnulation(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::Accepted)->getId();
        $this->login($team['admin']);
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Premier motif']);
        self::assertResponseStatusCodeSame(200);

        self::mockTime(new \DateTimeImmutable('2026-11-10 13:00:00', new \DateTimeZone('Europe/Paris')));
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Second motif']);

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $stored = $this->findLeave($id);
        self::assertSame(LeaveStatus::Cancelled, $stored?->getStatus());
        self::assertSame('Premier motif', $stored?->getCancellationReason());
        self::assertSame($this->nowTimestamp(), $stored?->getCancelledAt()?->getTimestamp());
    }

    public function testC14UneDemandeAnnuleeNePeutPlusEtreDecideeNiRetiree(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['admin']);
        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Doublon de demande']);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['marc']);
        $this->client->request('POST', '/api/leaves/'.$id.'/accept');
        $this->assertErrorResponse(409);

        $this->login($team['sophie']);
        $this->client->request('POST', '/api/leaves/'.$id.'/withdraw');
        $this->assertErrorResponse(409);

        self::assertSame(LeaveStatus::Cancelled, $this->findLeave($id)?->getStatus());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonAdmins(): iterable
    {
        yield 'le demandeur' => ['sophie'];
        yield 'son responsable' => ['marc'];
        yield 'manager d\'une autre équipe' => ['julie'];
        yield 'collègue salarié' => ['tom'];
    }

    #[DataProvider('nonAdmins')]
    public function testAutorisationUnNonAdminRecoit403SurUnCongeAccepte(string $reader): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::Accepted)->getId();
        $this->login($team[$reader]);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Motif valable']);

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertNotCancelled($id, LeaveStatus::Accepted);
    }

    public function testAutorisationLeResponsableNAnnulePasUneDemandeEnAttente403(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeave($team['sophie'], $this->day(10), $this->day(14))->getId();
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Motif valable']);

        $this->assertErrorResponse(403);
        $this->assertNotCancelled($id, LeaveStatus::Pending);
    }

    public function testAutorisationDemandeInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::Accepted)->getId();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves/'.($id + 1000).'/cancel', ['reason' => 'Motif valable']);

        $this->assertErrorResponse(404);
        $this->assertNotCancelled($id, LeaveStatus::Accepted);
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::Accepted)->getId();

        $this->jsonRequest('POST', '/api/leaves/'.$id.'/cancel', ['reason' => 'Motif valable']);

        $this->assertErrorResponse(401);
        $this->assertNotCancelled($id, LeaveStatus::Accepted);
    }

    private function assertNotCancelled(int $id, LeaveStatus $expected): void
    {
        $stored = $this->findLeave($id);
        self::assertInstanceOf(LeaveRequest::class, $stored);
        self::assertSame($expected, $stored->getStatus());
        self::assertNull($stored->getCancelledAt());
        self::assertNull($stored->getCancelledBy());
        self::assertNull($stored->getCancellationReason());
    }
}
