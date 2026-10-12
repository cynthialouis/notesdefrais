<?php

namespace App\Tests\Controller\Expense;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PATCH /api/expenses/{id} (propriétaire) — spec 002.
 */
final class UpdateExpenseTest extends ApiTestCase
{
    public function testC5LeProprietaireModifieTousLesChampsDUnBrouillon(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $expense = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas');
        $id = $expense->getId();
        $createdAt = $expense->getCreatedAt()->getTimestamp();
        $this->login($sophie);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, [
            'label' => '  Clavier USB  ',
            'amountCents' => 3999,
            'date' => '2026-09-28',
            'categorySlug' => 'materiel',
        ]);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('Clavier USB', $view['label']);
        self::assertSame(3999, $view['amountCents']);
        self::assertSame('2026-09-28', $view['date']);
        self::assertSame(['slug' => 'materiel', 'label' => 'Matériel'], $view['category']);
        self::assertSame('draft', $view['status']);
        self::assertNull($view['submittedAt']);

        $reloaded = $this->findExpense($id);
        self::assertNotNull($reloaded);
        self::assertSame('Clavier USB', $reloaded->getLabel());
        self::assertSame(3999, $reloaded->getAmountCents());
        self::assertSame('2026-09-28', $reloaded->getDate()->format('Y-m-d'));
        self::assertSame('materiel', $reloaded->getCategory()->getSlug());
        self::assertSame(ExpenseStatus::Draft, $reloaded->getStatus());
        self::assertSame($sophie->getId(), $reloaded->getOwner()->getId());
        self::assertSame($createdAt, $reloaded->getCreatedAt()->getTimestamp());
        self::assertSame(1, $this->countExpenses());
    }

    public function testC5UnBrouillonSeModifiePlusieursFois(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['amountCents' => 100]));
        self::assertResponseStatusCodeSame(200);
        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['amountCents' => 200]));
        self::assertResponseStatusCodeSame(200);

        self::assertSame(200, $this->responseJson()['amountCents']);
        self::assertSame(200, $this->findExpense($id)?->getAmountCents());
    }

    public function testC2LeMontantModifieEstConserveAuCentimePres(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie)->getId();
        $this->login($sophie);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['amountCents' => 1_000_000]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1_000_000, $this->responseJson()['amountCents']);
        self::assertSame(1_000_000, $this->findExpense($id)?->getAmountCents());
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
    public function testC7ModifierUneNoteQuiNEstPlusEnBrouillonRenvoie409SansModification(string $status): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas', ExpenseStatus::from($status))->getId();
        $this->login($sophie);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload());

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertUnchanged($id, ExpenseStatus::from($status));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'libellé fait d\'espaces' => [['label' => '    '], 'label'];
        yield 'libellé de 81 caractères' => [['label' => str_repeat('a', 81)], 'label'];
        yield 'montant 0' => [['amountCents' => 0], 'amountCents'];
        yield 'montant 10 000,01 €' => [['amountCents' => 1_000_001], 'amountCents'];
        yield 'montant à trois décimales' => [['amountCents' => 1250.5], 'amountCents'];
        yield 'date invalide' => [['date' => '2026-13-01'], 'date'];
        yield 'catégorie inconnue' => [['categorySlug' => 'inconnue'], 'categorySlug'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidFields')]
    public function testC3ModificationAvecChampInvalideRenvoie400SansModification(array $overrides, string $field): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas')->getId();
        $this->login($sophie);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload($overrides));

        $this->assertValidationError($field);
        $this->assertUnchanged($id, ExpenseStatus::Draft);
    }

    public function testC3ModificationAvecDateDeDemainRenvoie400(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas')->getId();
        $this->login($sophie);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['date' => $this->parisDate('+1 day')]));

        $this->assertValidationError('date');
        $this->assertUnchanged($id, ExpenseStatus::Draft);
    }

    public function testC5ModificationAvecCorpsIncompletRenvoie400(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas')->getId();
        $this->login($sophie);

        // PATCH attend le corps complet, comme POST.
        $this->jsonRequest('PATCH', '/api/expenses/'.$id, ['label' => 'Nouveau libellé']);

        $this->assertValidationError('amountCents', 'date', 'categorySlug');
        $this->assertUnchanged($id, ExpenseStatus::Draft);
    }

    public function testAutorisationModifierLaNoteDUnAutreRenvoie404SansModification(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee);
        $id = $this->createExpense($tom, 'Déjeuner client', 1250, '2026-10-01', 'repas')->getId();
        $this->login($sophie);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload());

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertUnchanged($id, ExpenseStatus::Draft);
        self::assertSame($tom->getId(), $this->findExpense($id)?->getOwner()->getId());
    }

    public function testAutorisationModifierUneNoteInexistanteRenvoie404(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('PATCH', '/api/expenses/999999', $this->expensePayload());

        $this->assertErrorResponse(404);
        self::assertSame(0, $this->countExpenses());
    }

    public function testAutorisationModificationSansSessionRenvoie401SansModification(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $id = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas')->getId();

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload());

        $this->assertErrorResponse(401);
        $this->assertUnchanged($id, ExpenseStatus::Draft);
    }

    /**
     * La note créée avec les valeurs par défaut des tests n'a pas bougé en base.
     */
    private function assertUnchanged(int $id, ExpenseStatus $status): void
    {
        $expense = $this->findExpense($id);
        self::assertInstanceOf(Expense::class, $expense);
        self::assertSame('Déjeuner client', $expense->getLabel());
        self::assertSame(1250, $expense->getAmountCents());
        self::assertSame('2026-10-01', $expense->getDate()->format('Y-m-d'));
        self::assertSame('repas', $expense->getCategory()->getSlug());
        self::assertSame($status, $expense->getStatus());
    }

    public function testRelectureUnCorpsInvalideSurUneNoteSoumiseEstRefuseEn400AvantLEtat(): void
    {
        $lea = $this->createUser('Léa', 'lea@demo.test', UserRole::Employee);
        $expense = $this->createExpense($lea, label: 'Avant', status: ExpenseStatus::Submitted);
        $this->login($lea);

        $this->jsonRequest('PATCH', '/api/expenses/'.$expense->getId(), $this->expensePayload(['label' => '']));

        $this->assertValidationError('label');
        self::assertSame('Avant', $this->findExpense((int) $expense->getId())?->getLabel());
    }
}
