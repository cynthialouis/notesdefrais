<?php

namespace App\Tests\Controller\Expense;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/expenses/{id}/approve (responsable du propriétaire) — spec 003 (C3, C6, C9).
 */
final class ApproveExpenseTest extends ApiTestCase
{
    public function testC3LeManagerValideUneNoteSoumiseEtEnregistreQuiEtQuand(): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], 'Déjeuner client', 1250, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $id = (int) $expense->getId();
        $this->login($team['marc']);
        $before = time();

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        self::assertResponseStatusCodeSame(200);
        $after = time();
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('approved', $view['status']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['decidedBy']);
        self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $view['owner']);
        $this->assertIsoBetween($view['decidedAt'], $before, $after);
        self::assertNull($view['rejectionReason']);
        self::assertFalse($view['decidable']);
        self::assertFalse($view['editable']);

        $stored = $this->findExpense($id);
        self::assertInstanceOf(Expense::class, $stored);
        self::assertSame(ExpenseStatus::Approved, $stored->getStatus());
        self::assertSame($team['marc']->getId(), $stored->getDecidedBy()?->getId());
        self::assertNotNull($stored->getDecidedAt());
        self::assertEqualsWithDelta((new \DateTimeImmutable($view['decidedAt']))->getTimestamp(), $stored->getDecidedAt()->getTimestamp(), 1);
        self::assertNull($stored->getRejectionReason());
        // La date de soumission est conservée.
        self::assertSame((new \DateTimeImmutable('2026-10-02T09:00:00+02:00'))->getTimestamp(), $stored->getSubmittedAt()?->getTimestamp());
    }

    public function testC3LAdminValideLaNoteDUnManagerQuiLuiEstRattache(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['marc'], status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['admin']);

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('approved', $this->responseJson()['status']);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Approved, $stored?->getStatus());
        self::assertSame($team['admin']->getId(), $stored?->getDecidedBy()?->getId());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notSubmittedStatuses(): iterable
    {
        yield 'validée' => ['approved'];
        yield 'rejetée' => ['rejected'];
    }

    #[DataProvider('notSubmittedStatuses')]
    public function testC6ValiderUneNoteNonSoumiseRenvoie409SansChangement(string $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::from($status))->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::from($status), $stored?->getStatus());
        self::assertNull($stored?->getDecidedBy());
        self::assertNull($stored?->getDecidedAt());
    }

    public function testC6UneSecondeValidationEstRefuseeEtLaPremiereConservee(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);
        $firstDecidedAt = $this->findExpense($id)?->getDecidedAt();
        self::assertNotNull($firstDecidedAt);

        sleep(1);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        $this->assertErrorResponse(409);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Approved, $stored?->getStatus());
        self::assertEquals($firstDecidedAt, $stored?->getDecidedAt());
        self::assertSame($team['marc']->getId(), $stored?->getDecidedBy()?->getId());
    }

    public function testC9UneNoteValideeNePeutPasEtreRejeteeEnsuite(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Changement d\'avis']);

        $this->assertErrorResponse(409);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Approved, $stored?->getStatus());
        self::assertNull($stored?->getRejectionReason());
    }

    public function testAutorisationLeProprietaireNeValidePasSaPropreNote403(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['marc'], status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Submitted, $stored?->getStatus());
        self::assertNull($stored?->getDecidedBy());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function strangers(): iterable
    {
        yield 'manager d\'une autre équipe' => ['julie'];
        yield 'collègue salarié' => ['tom'];
        yield 'admin, responsable du manager mais pas du salarié' => ['admin'];
    }

    #[DataProvider('strangers')]
    public function testAutorisationToutAutreQueLeResponsableRecoit404(string $reader): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Note de Sophie', status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team[$reader]);

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString('Note de Sophie', (string) $this->client->getResponse()->getContent());
        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Submitted, $stored?->getStatus());
        self::assertNull($stored?->getDecidedBy());
    }

    public function testAutorisationNoteInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.($id + 1000).'/approve');

        $this->assertErrorResponse(404);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        $this->assertErrorResponse(401);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }

    public function testCasLimiteValiderEnGetEstRefuse(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], status: ExpenseStatus::Submitted, submittedAt: '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$id.'/approve');

        $this->assertErrorResponse(405);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }

    /**
     * Un brouillon de l'équipe est invisible pour le responsable (404 en lecture, C12) :
     * le décider doit aussi répondre 404, sans révéler son existence par un 409.
     */
    public function testAutorisationDeciderUnBrouillonDeSonEquipeRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        $this->assertErrorResponse(404);
        self::assertSame(ExpenseStatus::Draft, $this->findExpense($id)?->getStatus());
    }
}
