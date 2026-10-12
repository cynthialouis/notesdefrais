<?php

namespace App\Tests\Controller\Expense;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;

/**
 * Note rejetée : le propriétaire la modifie, la supprime ou la resoumet — spec 003 (C8, cas limites).
 */
final class RejectedExpenseTest extends ApiTestCase
{
    public function testC8LeProprietaireModifieUneNoteRejetee(): void
    {
        $team = $this->createTeam();
        $id = $this->rejectedExpenseOfSophie($team);
        $this->login($team['sophie']);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['label' => '  Taxi corrigé  ', 'amountCents' => 1800]));

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame('Taxi corrigé', $view['label']);
        self::assertSame(1800, $view['amountCents']);
        self::assertSame('rejected', $view['status']);
        // Tant qu'elle n'est pas resoumise, le motif reste affiché.
        self::assertSame('Ticket manquant', $view['rejectionReason']);
        self::assertTrue($view['editable']);

        $stored = $this->findExpense($id);
        self::assertSame('Taxi corrigé', $stored?->getLabel());
        self::assertSame(1800, $stored?->getAmountCents());
        self::assertSame(ExpenseStatus::Rejected, $stored?->getStatus());
    }

    public function testC8ModificationInvalideDUneNoteRejeteeRenvoie400(): void
    {
        $team = $this->createTeam();
        $id = $this->rejectedExpenseOfSophie($team);
        $this->login($team['sophie']);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['amountCents' => 0]));

        $this->assertValidationError('amountCents');
        $stored = $this->findExpense($id);
        self::assertSame(1250, $stored?->getAmountCents());
        self::assertSame(ExpenseStatus::Rejected, $stored?->getStatus());
    }

    public function testC8LeProprietaireSupprimeUneNoteRejetee(): void
    {
        $team = $this->createTeam();
        $id = $this->rejectedExpenseOfSophie($team);
        $this->login($team['sophie']);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->findExpense($id));
    }

    public function testC8LaResoumissionRepasseEnSoumiseEtEffaceLaDecisionSurLaNote(): void
    {
        $team = $this->createTeam();
        $id = $this->rejectedExpenseOfSophie($team);
        $this->login($team['sophie']);
        $before = time();

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        self::assertResponseStatusCodeSame(200);
        $after = time();
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame('submitted', $view['status']);
        self::assertNull($view['rejectionReason']);
        self::assertNull($view['decidedAt']);
        self::assertNull($view['decidedBy']);
        self::assertFalse($view['editable']);
        $this->assertIsoBetween($view['submittedAt'], $before, $after);

        $stored = $this->findExpense($id);
        self::assertInstanceOf(Expense::class, $stored);
        self::assertSame(ExpenseStatus::Submitted, $stored->getStatus());
        self::assertNull($stored->getRejectionReason());
        self::assertNull($stored->getDecidedAt());
        self::assertNull($stored->getDecidedBy());
    }

    public function testCasLimiteLaNoteResoumiseRevientDansLaListeATraiterEtLHistoriqueGardeLeRejet(): void
    {
        $team = $this->createTeam();
        $id = $this->rejectedExpenseOfSophie($team);
        $this->login($team['sophie']);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses/'.$id.'/events');
        self::assertResponseStatusCodeSame(200);
        $events = $this->responseJson();
        self::assertSame(['created', 'submitted', 'rejected', 'submitted'], array_column($events, 'type'));
        self::assertSame('Ticket manquant', $events[2]['comment']);

        $this->login($team['marc']);
        $this->client->request('GET', '/api/team/expenses');
        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertSame([$id], array_column($list, 'id'));
        self::assertTrue($list[0]['decidable']);
        self::assertNull($list[0]['rejectionReason']);
    }

    public function testAutorisationUnCollegueNeModifieNiNeResoumetUneNoteRejetee(): void
    {
        $team = $this->createTeam();
        $id = $this->rejectedExpenseOfSophie($team);
        $this->login($team['tom']);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['label' => 'Piratée']));
        $this->assertErrorResponse(404);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        $this->assertErrorResponse(404);
        $this->client->request('DELETE', '/api/expenses/'.$id);
        $this->assertErrorResponse(404);

        $stored = $this->findExpense($id);
        self::assertSame('Note de Sophie', $stored?->getLabel());
        self::assertSame(ExpenseStatus::Rejected, $stored?->getStatus());
        self::assertSame('Ticket manquant', $stored?->getRejectionReason());
    }

    public function testAutorisationResoumissionSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = $this->rejectedExpenseOfSophie($team);
        $this->client->request('POST', '/api/auth/logout');

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $this->assertErrorResponse(401);
        self::assertSame(ExpenseStatus::Rejected, $this->findExpense($id)?->getStatus());
    }

    /**
     * Sophie crée et soumet sa note par l'API, Marc la rejette avec le motif « Ticket manquant ».
     * Marc reste connecté à la fin.
     *
     * @param array<string, \App\Entity\User> $team
     */
    private function rejectedExpenseOfSophie(array $team): int
    {
        $this->login($team['sophie']);
        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => 'Note de Sophie', 'amountCents' => 1250]));
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseJson()['id'];
        self::assertIsInt($id);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Ticket manquant']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(ExpenseStatus::Rejected, $this->findExpense($id)?->getStatus());

        return $id;
    }
}
