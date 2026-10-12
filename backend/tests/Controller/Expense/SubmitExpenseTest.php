<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/expenses/{id}/submit (propriétaire) — spec 002.
 */
final class SubmitExpenseTest extends ApiTestCase
{
    public function testC6SoumettreUnBrouillonLePasseASoumiseEtDateLaSoumission(): void
    {
        // Spec 003 C13 : seule une personne qui a un responsable peut soumettre.
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas')->getId();
        $this->login($sophie);
        $before = time();

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        self::assertResponseStatusCodeSame(200);
        $after = time();
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('submitted', $view['status']);
        self::assertSame('Déjeuner client', $view['label']);
        self::assertSame(1250, $view['amountCents']);
        self::assertIsString($view['submittedAt']);
        $submittedAt = (new \DateTimeImmutable($view['submittedAt']))->getTimestamp();
        self::assertGreaterThanOrEqual($before - 2, $submittedAt);
        self::assertLessThanOrEqual($after + 2, $submittedAt);

        $expense = $this->findExpense($id);
        self::assertNotNull($expense);
        self::assertSame(ExpenseStatus::Submitted, $expense->getStatus());
        self::assertNotNull($expense->getSubmittedAt());
        self::assertEqualsWithDelta($submittedAt, $expense->getSubmittedAt()->getTimestamp(), 1);
    }

    public function testC6LaNoteSoumiseApparaitDansLeFiltreSoumises(): void
    {
        // Spec 003 C13 : seule une personne qui a un responsable peut soumettre.
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses?status=submitted');
        self::assertSame([$id], array_column($this->responseJson(), 'id'));
        $this->client->request('GET', '/api/expenses?status=draft');
        self::assertSame([], $this->responseJson());
    }

    public function testC7SoumettreDeuxFoisRenvoie409EtNeChangePasLaDateDeSoumission(): void
    {
        // Spec 003 C13 : seule une personne qui a un responsable peut soumettre.
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);
        $firstSubmittedAt = $this->responseJson()['submittedAt'];
        $storedSubmittedAt = $this->findExpense($id)?->getSubmittedAt();
        self::assertNotNull($storedSubmittedAt);

        sleep(1);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $expense = $this->findExpense($id);
        self::assertNotNull($expense);
        self::assertSame(ExpenseStatus::Submitted, $expense->getStatus());
        self::assertEquals($storedSubmittedAt, $expense->getSubmittedAt());

        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertSame($firstSubmittedAt, $this->responseJson()['submittedAt']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonEditableStatuses(): iterable
    {
        yield 'soumise' => ['submitted'];
        yield 'validée' => ['approved'];
        // Spec 003 C8 : une note rejetée se modifie, se supprime et se resoumet (RejectedExpenseTest).
    }

    #[DataProvider('nonEditableStatuses')]
    public function testC7SoumettreUneNoteQuiNEstPlusEnBrouillonRenvoie409(string $status): void
    {
        // Spec 003 C13 : seule une personne qui a un responsable peut soumettre.
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas', ExpenseStatus::from($status))->getId();
        $this->login($sophie);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $expense = $this->findExpense($id);
        self::assertNotNull($expense);
        self::assertSame(ExpenseStatus::from($status), $expense->getStatus());
        self::assertNull($expense->getSubmittedAt());
    }

    public function testC7ApresSoumissionLaModificationEstRefusee(): void
    {
        // Spec 003 C13 : seule une personne qui a un responsable peut soumettre.
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas')->getId();
        $this->login($sophie);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['label' => 'Modifié après coup']));

        $this->assertErrorResponse(409);
        self::assertSame('Déjeuner client', $this->findExpense($id)?->getLabel());
    }

    public function testAutorisationSoumettreLaNoteDUnAutreRenvoie404SansChangerSonEtat(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee);
        $id = $this->createExpense($tom)->getId();
        $this->login($sophie);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        $expense = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Draft, $expense?->getStatus());
        self::assertNull($expense?->getSubmittedAt());
    }

    public function testAutorisationSoumettreUneNoteInexistanteRenvoie404(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);
        // La route existe : la note de l'utilisateur est bien lisible.
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', '/api/expenses/'.($id + 1000).'/submit');

        $this->assertErrorResponse(404);
        self::assertSame(ExpenseStatus::Draft, $this->findExpense($id)?->getStatus());
    }

    public function testCasLimiteSoumissionEnGetRefusee(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses/'.$id.'/submit');

        $this->assertErrorResponse(405);
        self::assertSame(ExpenseStatus::Draft, $this->findExpense($id)?->getStatus());
    }

    public function testAutorisationSoumissionSansSessionRenvoie401SansChangerLEtat(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $this->assertErrorResponse(401);
        $expense = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Draft, $expense?->getStatus());
        self::assertNull($expense?->getSubmittedAt());
    }

    public function testRevueLe409ExpliqueQueSeulUnBrouillonPeutEtreSoumis(): void
    {
        // Spec 003 C13 : Léa a un responsable, le 409 vient bien de l'état de la note.
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $lea = $this->createUser('Léa', 'lea@demo.test', UserRole::Employee, $marc);
        $expense = $this->createExpense($lea, status: ExpenseStatus::Submitted);
        $this->login($lea);

        $this->client->request('POST', '/api/expenses/'.$expense->getId().'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertSame('Seule une note en brouillon ou rejetée peut être soumise.', $error['message']);
    }
}
