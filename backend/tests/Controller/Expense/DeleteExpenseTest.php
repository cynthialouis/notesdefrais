<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * DELETE /api/expenses/{id} (propriétaire) — spec 002.
 */
final class DeleteExpenseTest extends ApiTestCase
{
    public function testC11SupprimerUnBrouillonRenvoie204EtLeSupprimeDefinitivement(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $kept = $this->createExpense($sophie, 'Autre brouillon')->getId();
        $this->login($sophie);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        self::assertNull($this->findExpense($id));
        self::assertNotNull($this->findExpense($kept));
        self::assertSame(1, $this->countExpenses());

        $this->client->request('GET', '/api/expenses');
        self::assertSame([$kept], array_column($this->responseJson(), 'id'));
    }

    public function testC11SupprimerDeuxFoisLeMemeBrouillonRenvoie404LaSecondeFois(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);
        $this->client->request('DELETE', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertNull($this->findExpense($id));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonEditableStatuses(): iterable
    {
        yield 'soumise' => ['submitted'];
        yield 'validée' => ['approved'];
        yield 'rejetée' => ['rejected'];
    }

    #[DataProvider('nonEditableStatuses')]
    public function testC12SupprimerUneNoteQuiNEstPlusEnBrouillonRenvoie409EtLaConserve(string $status): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas', ExpenseStatus::from($status))->getId();
        $this->login($sophie);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $expense = $this->findExpense($id);
        self::assertNotNull($expense);
        self::assertSame(ExpenseStatus::from($status), $expense->getStatus());
        self::assertSame(1, $this->countExpenses());
    }

    public function testC12UneNoteSoumiseParLApiNeSeSupprimePlus(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        $this->assertErrorResponse(409);
        self::assertNotNull($this->findExpense($id));
    }

    public function testAutorisationSupprimerLaNoteDUnAutreRenvoie404EtLaConserve(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee);
        $id = $this->createExpense($tom)->getId();
        $this->login($sophie);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertNotNull($this->findExpense($id));
    }

    public function testAutorisationSupprimerUneNoteInexistanteRenvoie404(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);
        // La route existe : la note de l'utilisateur est bien lisible.
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('DELETE', '/api/expenses/'.($id + 1000));

        $this->assertErrorResponse(404);
        self::assertNotNull($this->findExpense($id));
    }

    public function testAutorisationSuppressionSansSessionRenvoie401EtConserveLaNote(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();

        $this->client->request('DELETE', '/api/expenses/'.$id);

        $error = $this->assertErrorResponse(401);
        self::assertArrayNotHasKey('fields', $error);
        self::assertNotNull($this->findExpense($id));
    }
}
