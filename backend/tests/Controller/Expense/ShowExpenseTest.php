<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;

/**
 * GET /api/expenses/{id} (propriétaire) — spec 002.
 */
final class ShowExpenseTest extends ApiTestCase
{
    public function testC8LeProprietaireLitSaNote(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $expense = $this->createExpense($sophie, 'Hôtel Lyon', 12900, '2026-09-20', 'hebergement', ExpenseStatus::Submitted);
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($expense->getId(), $view['id']);
        self::assertSame('Hôtel Lyon', $view['label']);
        self::assertSame(12900, $view['amountCents']);
        self::assertSame('2026-09-20', $view['date']);
        self::assertSame(['slug' => 'hebergement', 'label' => 'Hébergement'], $view['category']);
        self::assertSame('submitted', $view['status']);
    }

    public function testAutorisationLaNoteDUnAutreRenvoie404(): void
    {
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee, $marc);
        $expense = $this->createExpense($tom, 'Note de Tom');
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString('Note de Tom', (string) $this->client->getResponse()->getContent());
    }

    public function testAutorisationLaNoteDUnAutreEtUneNoteInexistanteSontIndiscernables(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee);
        $expense = $this->createExpense($tom, 'Note de Tom');
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());
        $other = $this->assertErrorResponse(404);
        $otherBody = (string) $this->client->getResponse()->getContent();

        $this->client->request('GET', '/api/expenses/'.($expense->getId() + 1000));
        $missing = $this->assertErrorResponse(404);

        self::assertSame($missing, $other);
        self::assertSame((string) $this->client->getResponse()->getContent(), $otherBody);
    }

    public function testAutorisationLeManagerNeLitPasLaNoteDeSonEquipeDansCetteFeature(): void
    {
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $expense = $this->createExpense($sophie, 'Note de Sophie', 100, '2026-10-01', 'repas', ExpenseStatus::Submitted);
        $this->login($marc);

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        $this->assertErrorResponse(404);
    }

    public function testAutorisationIdentifiantNonNumeriqueRenvoie404(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);
        // La route existe : la note de l'utilisateur est bien lisible.
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses/abc');

        $this->assertErrorResponse(404);
    }

    public function testAutorisationLectureSansSessionRenvoie401(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $expense = $this->createExpense($sophie, 'Note de Sophie');

        $this->client->request('GET', '/api/expenses/'.$expense->getId());

        $error = $this->assertErrorResponse(401);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString('Note de Sophie', (string) $this->client->getResponse()->getContent());
    }

    public function testRelectureLeServeurIndiqueSiLaNoteEstModifiable(): void
    {
        $lea = $this->createUser('Léa', 'lea@demo.test', UserRole::Employee);
        $draft = $this->createExpense($lea);
        $submitted = $this->createExpense($lea, status: ExpenseStatus::Submitted);
        $this->login($lea);

        $this->client->request('GET', '/api/expenses/'.$draft->getId());
        self::assertTrue($this->responseJson()['editable'] ?? null);

        $this->client->request('GET', '/api/expenses/'.$submitted->getId());
        self::assertFalse($this->responseJson()['editable'] ?? null);
    }
}
