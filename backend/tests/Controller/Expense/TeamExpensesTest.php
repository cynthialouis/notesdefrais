<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;

/**
 * GET /api/team/expenses (manager et plus) : notes soumises à traiter — spec 003 (C1, C2, C7).
 */
final class TeamExpensesTest extends ApiTestCase
{
    public function testC1LeManagerVoitLesNotesSoumisesDeSonEquipeDirecteSeulement(): void
    {
        $team = $this->createTeam();
        $sophieSubmitted = $this->createExpense($team['sophie'], 'Note Sophie', 1000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $tomSubmitted = $this->createExpense($team['tom'], 'Note Tom', 2000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-03T09:00:00+02:00');
        // Jamais : brouillons, validées, rejetées, autre équipe, notes du manager lui-même.
        $this->createExpense($team['sophie'], 'Brouillon Sophie');
        $this->createExpense($team['sophie'], 'Validée Sophie', 100, '2026-09-01', 'repas', ExpenseStatus::Approved, '2026-09-02T09:00:00+02:00');
        $this->createExpense($team['tom'], 'Rejetée Tom', 100, '2026-09-01', 'repas', ExpenseStatus::Rejected, '2026-09-02T09:00:00+02:00');
        $this->createExpense($team['ines'], 'Note Inès', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-01T09:00:00+02:00');
        $this->createExpense($team['marc'], 'Note de Marc', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-01T09:00:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/team/expenses');

        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertTrue(array_is_list($list));
        self::assertSame([$sophieSubmitted->getId(), $tomSubmitted->getId()], array_column($list, 'id'));
        self::assertSame(['submitted', 'submitted'], array_column($list, 'status'));
    }

    public function testC1LesPlusAnciennesSoumissionsDAbordPuisIdCroissant(): void
    {
        $team = $this->createTeam();
        $late = $this->createExpense($team['sophie'], 'Tardive', 100, '2026-09-01', 'repas', ExpenseStatus::Submitted, '2026-10-05T10:00:00+02:00');
        $earlyA = $this->createExpense($team['tom'], 'Ancienne A', 100, '2026-10-04', 'repas', ExpenseStatus::Submitted, '2026-10-01T10:00:00+02:00');
        $earlyB = $this->createExpense($team['sophie'], 'Ancienne B', 100, '2026-08-01', 'repas', ExpenseStatus::Submitted, '2026-10-01T10:00:00+02:00');
        $middle = $this->createExpense($team['tom'], 'Milieu', 100, '2026-10-06', 'repas', ExpenseStatus::Submitted, '2026-10-03T10:00:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/team/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [$earlyA->getId(), $earlyB->getId(), $middle->getId(), $late->getId()],
            array_column($this->responseJson(), 'id'),
        );
    }

    public function testC2ChaqueNoteAfficheAuteurLibelleDateCategorieMontantEtSoumission(): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], 'Hôtel Lyon', 12900, '2026-09-20', 'hebergement', ExpenseStatus::Submitted, '2026-09-21T08:30:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/team/expenses');

        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertCount(1, $list);
        $view = $list[0];
        $this->assertExpenseView($view);
        self::assertSame($expense->getId(), $view['id']);
        self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $view['owner']);
        self::assertSame('Hôtel Lyon', $view['label']);
        self::assertSame('2026-09-20', $view['date']);
        self::assertSame(['slug' => 'hebergement', 'label' => 'Hébergement'], $view['category']);
        self::assertSame(12900, $view['amountCents']);
        self::assertIsString($view['submittedAt']);
        self::assertSame(
            (new \DateTimeImmutable('2026-09-21T08:30:00+02:00'))->getTimestamp(),
            (new \DateTimeImmutable($view['submittedAt']))->getTimestamp(),
        );
        // Lue par son manager : décidable, pas modifiable, pas encore décidée.
        self::assertTrue($view['decidable']);
        self::assertFalse($view['editable']);
        self::assertNull($view['decidedAt']);
        self::assertNull($view['decidedBy']);
        self::assertNull($view['rejectionReason']);
    }

    public function testC1ListeVideSiAucuneNoteATraiter(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['sophie'], 'Brouillon');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/team/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->responseJson());
    }

    public function testC1LAdminVoitLesNotesSoumisesDesManagersQuiLuiSontRattaches(): void
    {
        $team = $this->createTeam();
        $marcNote = $this->createExpense($team['marc'], 'Note de Marc', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-01T09:00:00+02:00');
        $julieNote = $this->createExpense($team['julie'], 'Note de Julie', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        // Les salariés ne sont pas rattachés directement à l'admin.
        $this->createExpense($team['sophie'], 'Note Sophie', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-09-30T09:00:00+02:00');
        $this->login($team['admin']);

        $this->client->request('GET', '/api/team/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$marcNote->getId(), $julieNote->getId()], array_column($this->responseJson(), 'id'));
    }

    public function testC7UneNoteDecideeSortDeLaListeATraiter(): void
    {
        $team = $this->createTeam();
        $approved = $this->createExpense($team['sophie'], 'À valider', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-01T09:00:00+02:00');
        $rejected = $this->createExpense($team['tom'], 'À rejeter', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $kept = $this->createExpense($team['tom'], 'Restante', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-03T09:00:00+02:00');
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.$approved->getId().'/approve');
        self::assertResponseStatusCodeSame(200);
        $this->jsonRequest('POST', '/api/expenses/'.$rejected->getId().'/reject', ['reason' => 'Justificatif illisible']);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/team/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$kept->getId()], array_column($this->responseJson(), 'id'));
    }

    public function testAutorisationUnSalarieRecoit403(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['sophie'], 'Note Sophie', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-01T09:00:00+02:00');
        $this->login($team['tom']);

        $this->client->request('GET', '/api/team/expenses');

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString('Note Sophie', (string) $this->client->getResponse()->getContent());
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['sophie'], 'Note Sophie', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-01T09:00:00+02:00');

        $this->client->request('GET', '/api/team/expenses');

        $this->assertErrorResponse(401);
        self::assertStringNotContainsString('Note Sophie', (string) $this->client->getResponse()->getContent());
    }
}
