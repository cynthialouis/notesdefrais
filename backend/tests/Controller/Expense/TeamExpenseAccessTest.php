<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * GET /api/expenses/{id} par le responsable du propriétaire, et vue par lecteur — spec 003 (C12, C8).
 */
final class TeamExpenseAccessTest extends ApiTestCase
{
    public function testC12LeManagerLitLeDetailDUneNoteSoumiseDeSonEquipe(): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], 'Hôtel Lyon', 12900, '2026-09-20', 'hebergement', ExpenseStatus::Submitted, '2026-09-21T08:30:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($expense->getId(), $view['id']);
        self::assertSame('Hôtel Lyon', $view['label']);
        self::assertSame(12900, $view['amountCents']);
        self::assertSame('submitted', $view['status']);
        self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $view['owner']);
        self::assertTrue($view['decidable']);
        self::assertFalse($view['editable']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function decidedStatuses(): iterable
    {
        yield 'validée' => ['approved'];
        yield 'rejetée' => ['rejected'];
    }

    #[DataProvider('decidedStatuses')]
    public function testC12LeManagerLitUneNoteDecideeDeSonEquipeSansPouvoirDecider(string $status): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], status: ExpenseStatus::from($status), submittedAt: '2026-09-21T08:30:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($status, $view['status']);
        self::assertFalse($view['decidable']);
        self::assertFalse($view['editable']);
    }

    public function testC12LeProprietaireNEstJamaisDecideurDeSaNote(): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['marc'], status: ExpenseStatus::Submitted, submittedAt: '2026-09-21T08:30:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertFalse($view['decidable']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['owner']);
    }

    public function testC8LeProprietaireVoitLeMotifEtPeutModifierSaNoteRejetee(): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], status: ExpenseStatus::Submitted, submittedAt: '2026-09-21T08:30:00+02:00');
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$expense->getId().'/reject', ['reason' => 'Ticket manquant']);
        self::assertResponseStatusCodeSame(200);
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame('rejected', $view['status']);
        self::assertSame('Ticket manquant', $view['rejectionReason']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['decidedBy']);
        self::assertIsString($view['decidedAt']);
        self::assertTrue($view['editable']);
        self::assertFalse($view['decidable']);

        $this->client->request('GET', '/api/expenses?status=rejected');
        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertSame([$expense->getId()], array_column($list, 'id'));
        self::assertSame('Ticket manquant', $list[0]['rejectionReason']);
        self::assertTrue($list[0]['editable']);
    }

    public function testAutorisationLeManagerNeLitPasUnBrouillonDeSonEquipe(): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], 'Brouillon de Sophie');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString('Brouillon de Sophie', (string) $this->client->getResponse()->getContent());
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
        $expense = $this->createExpense($team['sophie'], 'Note de Sophie', status: ExpenseStatus::Submitted, submittedAt: '2026-09-21T08:30:00+02:00');
        $this->login($team[$reader]);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        $this->assertErrorResponse(404);
        self::assertStringNotContainsString('Note de Sophie', (string) $this->client->getResponse()->getContent());
    }

    public function testAutorisationLeManagerNeModifiePasLaNoteDeSonEquipe(): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], 'Note de Sophie', status: ExpenseStatus::Rejected, submittedAt: '2026-09-21T08:30:00+02:00');
        $id = (int) $expense->getId();
        $this->login($team['marc']);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['label' => 'Modifiée par Marc']));
        $this->assertErrorResponse(404);
        $this->client->request('DELETE', '/api/expenses/'.$id);
        $this->assertErrorResponse(404);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        $this->assertErrorResponse(404);

        $stored = $this->findExpense($id);
        self::assertSame('Note de Sophie', $stored?->getLabel());
        self::assertSame(ExpenseStatus::Rejected, $stored?->getStatus());
    }
}
