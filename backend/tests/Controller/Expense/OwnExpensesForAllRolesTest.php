<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * C13 : tout utilisateur connecté (salarié, manager, admin) gère ses propres notes — spec 002.
 */
final class OwnExpensesForAllRolesTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function roles(): iterable
    {
        yield 'salarié' => ['employee'];
        yield 'manager' => ['manager'];
        yield 'admin' => ['admin'];
    }

    #[DataProvider('roles')]
    public function testC13ChaqueRoleCreeListeModifieEtSoumetSesNotes(string $role): void
    {
        // Spec 003 C13 : salarié et manager ont un responsable ; l'admin n'en a pas et ne peut pas soumettre.
        $responsible = 'admin' === $role ? null : $this->createUser('Responsable', 'responsable@demo.test', UserRole::Admin);
        $user = $this->createUser('Utilisateur', $role.'@demo.test', UserRole::from($role), $responsible);
        $other = $this->createUser('Autre', 'autre@demo.test', UserRole::Employee);
        $this->createExpense($other, 'Note d\'un autre');
        $this->login($user);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload());
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseJson()['id'];
        self::assertSame($user->getId(), $this->findExpense($id)?->getOwner()->getId());

        $this->client->request('GET', '/api/expenses');
        self::assertResponseStatusCodeSame(200);
        self::assertSame([$id], array_column($this->responseJson(), 'id'));

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['label' => 'Libellé corrigé']));
        self::assertResponseStatusCodeSame(200);
        self::assertSame('Libellé corrigé', $this->findExpense($id)?->getLabel());

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        if ('admin' === $role) {
            $this->assertErrorResponse(409);
            self::assertSame(ExpenseStatus::Draft, $this->findExpense($id)?->getStatus());

            return;
        }
        self::assertResponseStatusCodeSame(200);
        self::assertSame('submitted', $this->responseJson()['status']);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }

    #[DataProvider('roles')]
    public function testC13ChaqueRoleSupprimeSonBrouillon(string $role): void
    {
        $user = $this->createUser('Utilisateur', $role.'@demo.test', UserRole::from($role));
        $id = $this->createExpense($user)->getId();
        $this->login($user);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->findExpense($id));
    }

    public function testC13UnAdminNeVoitPasLesNotesDesAutres(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($alice);

        $this->client->request('GET', '/api/expenses');
        self::assertSame([], $this->responseJson());

        $this->client->request('GET', '/api/expenses/'.$id);
        $this->assertErrorResponse(404);

        $this->client->request('DELETE', '/api/expenses/'.$id);
        $this->assertErrorResponse(404);
        self::assertNotNull($this->findExpense($id));
    }
}
