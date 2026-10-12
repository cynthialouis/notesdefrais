<?php

namespace App\Tests\Controller\Expense;

use App\Entity\ExpenseEvent;
use App\Enum\ExpenseEventType;
use App\Enum\ExpenseStatus;
use App\Repository\ExpenseEventRepository;
use App\Tests\Support\ApiTestCase;

/**
 * C13 : une personne sans responsable (l'admin) ne peut pas soumettre — spec 003.
 */
final class SubmitWithoutResponsibleTest extends ApiTestCase
{
    public function testC13LAdminNePeutPasSoumettreEtLeMessageEstExplicite(): void
    {
        $team = $this->createTeam();
        $this->login($team['admin']);
        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => 'Note de l\'admin']));
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseJson()['id'];

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        // Message propre à ce cas : ni le message générique du 409, ni celui d'un mauvais état.
        self::assertNotSame('Action impossible dans l\'état actuel.', $error['message']);
        self::assertNotSame('Seul un brouillon peut être soumis.', $error['message']);

        $stored = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Draft, $stored?->getStatus());
        self::assertNull($stored?->getSubmittedAt());
        $events = static::getContainer()->get(ExpenseEventRepository::class)->findBy(['expense' => $id]);
        self::assertSame([ExpenseEventType::Created], array_map(static fn (ExpenseEvent $e): ExpenseEventType => $e->getType(), $events));
    }

    public function testC13UnManagerRattacheALAdminSoumetEtLAdminVoitSaNote(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['marc'], 'Note de Marc')->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('submitted', $this->responseJson()['status']);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());

        $this->login($team['admin']);
        $this->client->request('GET', '/api/team/expenses');
        self::assertResponseStatusCodeSame(200);
        self::assertSame([$id], array_column($this->responseJson(), 'id'));
    }
}
