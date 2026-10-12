<?php

namespace App\Tests\Controller\Expense;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;

/**
 * Note validée : plus aucune modification, suppression, soumission ni décision — spec 003 (C9).
 */
final class ApprovedExpenseTest extends ApiTestCase
{
    public function testC9LeProprietaireNeModifiePasUneNoteValidee(): void
    {
        $team = $this->createTeam();
        $id = $this->approvedExpenseOfSophie($team);
        $this->login($team['sophie']);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['label' => 'Modifiée après validation']));

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertStillApproved($id);
    }

    public function testC9LeProprietaireNeSupprimePasUneNoteValidee(): void
    {
        $team = $this->createTeam();
        $id = $this->approvedExpenseOfSophie($team);
        $this->login($team['sophie']);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        $this->assertErrorResponse(409);
        $this->assertStillApproved($id);
    }

    public function testC9LeProprietaireNeResoumetPasUneNoteValidee(): void
    {
        $team = $this->createTeam();
        $id = $this->approvedExpenseOfSophie($team);
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $this->assertErrorResponse(409);
        $this->assertStillApproved($id);
    }

    public function testC9LeManagerNeRedecidePasUneNoteValidee(): void
    {
        $team = $this->createTeam();
        $id = $this->approvedExpenseOfSophie($team);
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        $this->assertErrorResponse(409);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Finalement non']);
        $this->assertErrorResponse(409);

        $this->assertStillApproved($id);
    }

    public function testC9UneNoteValideeLueParSonProprietaireNEstNiModifiableNiDecidable(): void
    {
        $team = $this->createTeam();
        $id = $this->approvedExpenseOfSophie($team);
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame('approved', $view['status']);
        self::assertFalse($view['editable']);
        self::assertFalse($view['decidable']);
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $view['decidedBy']);
        self::assertIsString($view['decidedAt']);
        self::assertNull($view['rejectionReason']);
    }

    /**
     * @param array<string, \App\Entity\User> $team
     */
    private function approvedExpenseOfSophie(array $team): int
    {
        $id = (int) $this->createExpense($team['sophie'], 'Note de Sophie', 1250, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);

        return $id;
    }

    private function assertStillApproved(int $id): void
    {
        $stored = $this->findExpense($id);
        self::assertInstanceOf(Expense::class, $stored);
        self::assertSame(ExpenseStatus::Approved, $stored->getStatus());
        self::assertSame('Note de Sophie', $stored->getLabel());
        self::assertSame(1250, $stored->getAmountCents());
        self::assertNull($stored->getRejectionReason());
    }
}
