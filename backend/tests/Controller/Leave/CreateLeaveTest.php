<?php

namespace App\Tests\Controller\Leave;

use App\Entity\LeaveRequest;
use App\Enum\LeaveStatus;
use App\Tests\Support\LeaveTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/leaves, corps { startDate, endDate } (connecté, avec un responsable) — spec 007 (C1, C2, C4, C5).
 * Ordre des refus (research D5) : 400 validation, puis 409 sans responsable, puis 409 chevauchement.
 */
final class CreateLeaveTest extends LeaveTestCase
{
    public function testC1UnSalarieDemandeUnCongeEnregistreEnAttenteASonNom(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(5), 'endDate' => $this->day(9)]);

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        $this->assertLeaveView($view);
        self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $view['requester']);
        self::assertSame($this->day(5), $view['startDate']);
        self::assertSame($this->day(9), $view['endDate']);
        self::assertSame('pending', $view['status']);
        $this->assertIsNow($view['createdAt']);
        foreach (['decidedAt', 'decidedBy', 'refusalReason', 'cancelledAt', 'cancelledBy', 'cancellationReason'] as $key) {
            self::assertNull($view[$key], $key.' doit être null.');
        }
        // Lue par le demandeur : il peut la retirer, pas la décider ni l'annuler
        self::assertTrue($view['withdrawable']);
        self::assertFalse($view['decidable']);
        self::assertFalse($view['cancellable']);

        $stored = $this->findLeave($view['id']);
        self::assertInstanceOf(LeaveRequest::class, $stored);
        self::assertSame($team['sophie']->getId(), $stored->getRequester()->getId());
        self::assertSame($this->day(5), $stored->getStartDate()->format('Y-m-d'));
        self::assertSame($this->day(9), $stored->getEndDate()->format('Y-m-d'));
        self::assertSame(LeaveStatus::Pending, $stored->getStatus());
        self::assertSame($this->nowTimestamp(), $stored->getCreatedAt()->getTimestamp());
        self::assertNull($stored->getDecidedAt());
        self::assertNull($stored->getDecidedBy());
        self::assertNull($stored->getCancelledAt());
        self::assertSame(1, $this->countLeaves());
    }

    public function testC1UnManagerRattacheALAdminDemandeUnConge(): void
    {
        $team = $this->createTeam();
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(1), 'endDate' => $this->day(2)]);

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame('pending', $view['status']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['requester']);
        $stored = $this->findLeave($view['id']);
        self::assertSame($team['marc']->getId(), $stored?->getRequester()->getId());
        self::assertSame(LeaveStatus::Pending, $stored?->getStatus());
    }

    public function testC1LeStatutLeDemandeurEtLaDecisionEnvoyesParLeClientSontIgnoresOuRefuses(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', [
            'startDate' => $this->day(5),
            'endDate' => $this->day(6),
            'status' => 'accepted',
            'requesterId' => $team['tom']->getId(),
            'decidedBy' => $team['marc']->getId(),
        ]);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [201, 400], 'Un champ en trop est soit ignoré, soit refusé.');
        if (201 === $status) {
            $view = $this->responseJson();
            self::assertSame('pending', $view['status']);
            self::assertSame($team['sophie']->getId(), $view['requester']['id']);
            self::assertNull($view['decidedBy']);
            $stored = $this->findLeave($view['id']);
            self::assertSame($team['sophie']->getId(), $stored?->getRequester()->getId());
            self::assertSame(LeaveStatus::Pending, $stored?->getStatus());
        } else {
            self::assertSame(0, $this->countLeaves());
        }
    }

    public function testCasLimiteUnSeulJourDebutEgalFinEstAccepte(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(3), 'endDate' => $this->day(3)]);

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame($this->day(3), $view['startDate']);
        self::assertSame($this->day(3), $view['endDate']);
        self::assertSame(1, $this->countLeaves());
    }

    public function testC2DebutAujourdhuiEstAccepte(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => self::TODAY, 'endDate' => self::TODAY]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(self::TODAY, $this->responseJson()['startDate']);
        self::assertSame(self::TODAY, $this->findLeave($this->responseJson()['id'])?->getStartDate()->format('Y-m-d'));
    }

    public function testC2DebutHierEstRefuseSurLeChampStartDate(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(-1), 'endDate' => $this->day(2)]);

        $fields = $this->assertValidationError('startDate');
        self::assertArrayNotHasKey('endDate', $fields);
        self::assertSame(0, $this->countLeaves());
    }

    /**
     * À 00 h 30 le 10/11 à Paris, il est encore le 09/11 en UTC : « aujourd'hui » est le 10/11 (heure de Paris).
     */
    public function testC2AujourdhuiSEntendEnHeureDeParis(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-11-10 00:30:00', new \DateTimeZone('Europe/Paris')));
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => '2026-11-09', 'endDate' => '2026-11-12']);
        $this->assertValidationError('startDate');
        self::assertSame(0, $this->countLeaves());

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => '2026-11-10', 'endDate' => '2026-11-12']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $this->countLeaves());
    }

    public function testC2FinAnterieureAuDebutEstRefuseeSurLeChampEndDate(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(5), 'endDate' => $this->day(4)]);

        $fields = $this->assertValidationError('endDate');
        self::assertArrayNotHasKey('startDate', $fields);
        self::assertSame(0, $this->countLeaves());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidBodies(): iterable
    {
        $valid = ['startDate' => '2026-11-15', 'endDate' => '2026-11-16'];

        yield 'corps vide' => [[], ['startDate', 'endDate']];
        yield 'début absent' => [['endDate' => $valid['endDate']], ['startDate']];
        yield 'fin absente' => [['startDate' => $valid['startDate']], ['endDate']];
        yield 'début null' => [['startDate' => null] + $valid, ['startDate']];
        yield 'fin null' => [['endDate' => null] + $valid, ['endDate']];
        yield 'début vide' => [['startDate' => ''] + $valid, ['startDate']];
        yield 'fin vide' => [['endDate' => ''] + $valid, ['endDate']];
        yield 'début au mauvais format' => [['startDate' => '15/11/2026'] + $valid, ['startDate']];
        yield 'fin au mauvais format' => [['endDate' => '16/11/2026'] + $valid, ['endDate']];
        yield 'début inexistant' => [['startDate' => '2027-02-30', 'endDate' => '2027-03-02'], ['startDate']];
        yield 'fin inexistante' => [['startDate' => '2026-11-15', 'endDate' => '2026-11-31'], ['endDate']];
        yield 'début non textuel' => [['startDate' => 'pas-une-date'] + $valid, ['startDate']];
        yield 'début avec une heure' => [['startDate' => '2026-11-15T10:00:00'] + $valid, ['startDate']];
        yield 'début dans le passé et fin avant le début' => [['startDate' => '2026-11-01', 'endDate' => '2026-10-30'], ['startDate', 'endDate']];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $fields
     */
    #[DataProvider('invalidBodies')]
    public function testC2DateAbsenteOuInvalideRenvoie400SurChaqueChampEnCause(array $body, array $fields): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', $body);

        $this->assertValidationError(...$fields);
        self::assertSame(0, $this->countLeaves());
    }

    public function testC2UneDateNonTextuelleRenvoie400(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => 20261115, 'endDate' => '2026-11-16']);

        $this->assertErrorResponse(400);
        self::assertSame(0, $this->countLeaves());
    }

    /**
     * Le chevauchement compte même d'un seul jour (bornes comprises), avec une demande en attente ou acceptée.
     *
     * @return iterable<string, array{string, int, int}>
     */
    public static function overlappingPeriods(): iterable
    {
        // demande existante : du jour +10 au jour +14
        foreach (['pending' => 'en attente', 'accepted' => 'acceptée'] as $status => $label) {
            yield "fin de l'existante = début de la nouvelle ($label)" => [$status, 14, 18];
            yield "fin de la nouvelle = début de l'existante ($label)" => [$status, 6, 10];
            yield "nouvelle incluse dans l'existante ($label)" => [$status, 11, 12];
            yield "nouvelle qui englobe l'existante ($label)" => [$status, 8, 20];
            yield "même période ($label)" => [$status, 10, 14];
        }
    }

    #[DataProvider('overlappingPeriods')]
    public function testC4UnePeriodeQuiChevaucheUneDemandeEnAttenteOuAccepteeRenvoie409(string $status, int $start, int $end): void
    {
        $team = $this->createTeam();
        $existing = $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::from($status));
        $existingId = (int) $existing->getId();
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day($start), 'endDate' => $this->day($end)]);

        $this->assertExplicitConflict();
        self::assertSame(1, $this->countLeaves());
        self::assertSame(LeaveStatus::from($status), $this->findLeave($existingId)?->getStatus());
    }

    public function testC4LeMessageDuChevauchementEstDistinctDeCeluiSansResponsable(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['sophie'], $this->day(10), $this->day(14));
        $this->login($team['sophie']);
        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(14), 'endDate' => $this->day(18)]);
        $overlapMessage = $this->assertExplicitConflict();

        $this->login($team['admin']);
        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(30), 'endDate' => $this->day(31)]);
        $noResponsibleMessage = $this->assertExplicitConflict();

        self::assertNotSame($noResponsibleMessage, $overlapMessage);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonBlockingStatuses(): iterable
    {
        yield 'refusée' => ['refused'];
        yield 'retirée' => ['withdrawn'];
        yield 'annulée' => ['cancelled'];
    }

    #[DataProvider('nonBlockingStatuses')]
    public function testC4UneDemandeRefuseeRetireeOuAnnuleeNeBloquePasLaPeriode(string $status): void
    {
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['sophie'], $this->day(10), $this->day(14), LeaveStatus::from($status), $team['admin']);
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(10), 'endDate' => $this->day(14)]);

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame('pending', $view['status']);
        self::assertSame(2, $this->countLeaves());
        self::assertSame(LeaveStatus::Pending, $this->findLeave($view['id'])?->getStatus());
    }

    public function testC4UnePeriodeAccoleeSansJourCommunEstAcceptee(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['sophie'], $this->day(10), $this->day(14));
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(15), 'endDate' => $this->day(18)]);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(5), 'endDate' => $this->day(9)]);
        self::assertResponseStatusCodeSame(201);

        self::assertSame(3, $this->countLeaves());
    }

    public function testC4LaDemandeDUnAutreSurLaMemePeriodeNeBloquePas(): void
    {
        $team = $this->createTeam();
        $this->createLeaveInStatus($team['tom'], $this->day(10), $this->day(14), LeaveStatus::Accepted);
        $this->createLeave($team['ines'], $this->day(10), $this->day(14));
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(10), 'endDate' => $this->day(14)]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($team['sophie']->getId(), $this->responseJson()['requester']['id']);
        self::assertSame(3, $this->countLeaves());
    }

    public function testC4UneDemandeRetireePuisRefaiteSurLaMemePeriode(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);
        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(10), 'endDate' => $this->day(14)]);
        self::assertResponseStatusCodeSame(201);
        $firstId = $this->responseJson()['id'];

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(12), 'endDate' => $this->day(13)]);
        $this->assertExplicitConflict();

        $this->client->request('POST', '/api/leaves/'.$firstId.'/withdraw');
        self::assertResponseStatusCodeSame(200);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(12), 'endDate' => $this->day(13)]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(2, $this->countLeaves());
    }

    public function testC5LAdminSansResponsableNePeutPasDemanderDeCongeEtLeMessageEstExplicite(): void
    {
        $team = $this->createTeam();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(5), 'endDate' => $this->day(6)]);

        $message = $this->assertExplicitConflict();
        self::assertNotSame('', trim($message));
        self::assertSame(0, $this->countLeaves());
    }

    public function testC5OrdreDesRefusLaValidationPasseAvantLAbsenceDeResponsable(): void
    {
        $team = $this->createTeam();
        $this->login($team['admin']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(-3), 'endDate' => $this->day(6)]);

        $this->assertValidationError('startDate');
        self::assertSame(0, $this->countLeaves());
    }

    public function testC5OrdreDesRefusLAbsenceDeResponsablePasseAvantLeChevauchement(): void
    {
        $team = $this->createTeam();
        $this->login($team['admin']);
        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(30), 'endDate' => $this->day(31)]);
        $noResponsibleMessage = $this->assertExplicitConflict();

        // Demande de l'admin posée directement en base : la nouvelle la chevaucherait
        $this->createLeave($team['admin'], $this->day(5), $this->day(6));
        $this->login($team['admin']);
        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(5), 'endDate' => $this->day(6)]);

        self::assertSame($noResponsibleMessage, $this->assertExplicitConflict());
        self::assertSame(1, $this->countLeaves());
    }

    public function testC4OrdreDesRefusLaValidationPasseAvantLeChevauchement(): void
    {
        $team = $this->createTeam();
        $this->createLeave($team['sophie'], $this->day(10), $this->day(14));
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(14), 'endDate' => $this->day(12)]);

        $this->assertValidationError('endDate');
        self::assertSame(1, $this->countLeaves());
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $this->createTeam();

        $this->jsonRequest('POST', '/api/leaves', ['startDate' => $this->day(5), 'endDate' => $this->day(6)]);

        $this->assertErrorResponse(401);
        self::assertSame(0, $this->countLeaves());
    }
}
