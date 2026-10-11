<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * GET /api/expenses (connecté) — spec 002.
 */
final class ListExpensesTest extends ApiTestCase
{
    public function testC8ListeLesNotesDeLUtilisateurAvecTousLesChamps(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $expense = $this->createExpense($sophie, 'Déjeuner client', 1250, '2026-10-01', 'repas');
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses');

        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertTrue(array_is_list($list));
        self::assertCount(1, $list);
        $this->assertExpenseView($list[0]);
        self::assertSame($expense->getId(), $list[0]['id']);
        self::assertSame('Déjeuner client', $list[0]['label']);
        self::assertSame(1250, $list[0]['amountCents']);
        self::assertSame('2026-10-01', $list[0]['date']);
        self::assertSame(['slug' => 'repas', 'label' => 'Repas'], $list[0]['category']);
        self::assertSame('draft', $list[0]['status']);
        self::assertNull($list[0]['submittedAt']);
    }

    public function testC8LesPlusRecentesDAbordParDateDecroissantePuisIdDecroissant(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $a = $this->createExpense($sophie, 'Note A', 100, '2026-09-01');
        $b = $this->createExpense($sophie, 'Note B', 200, '2026-10-05');
        $c = $this->createExpense($sophie, 'Note C', 300, '2026-10-05');
        $d = $this->createExpense($sophie, 'Note D', 400, '2026-08-15');
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [$c->getId(), $b->getId(), $a->getId(), $d->getId()],
            array_column($this->responseJson(), 'id'),
        );
    }

    public function testC8NeListeJamaisLesNotesDesAutres(): void
    {
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee, $marc);
        $mine = $this->createExpense($sophie, 'Ma note');
        $this->createExpense($tom, 'Note de Tom', 999, '2026-10-10');
        $this->createExpense($marc, 'Note de Marc', 999, '2026-10-10', 'repas', ExpenseStatus::Submitted);
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses');

        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertSame([$mine->getId()], array_column($list, 'id'));
        self::assertSame(['Ma note'], array_column($list, 'label'));
    }

    public function testC8AucuneNoteRenvoieUneListeVide(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->responseJson());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function statuses(): iterable
    {
        foreach (['draft', 'submitted', 'approved', 'rejected'] as $status) {
            yield $status => [$status];
        }
    }

    #[DataProvider('statuses')]
    public function testC9FiltreParEtat(string $status): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee);
        $expected = [];
        foreach (ExpenseStatus::cases() as $i => $case) {
            $expense = $this->createExpense($sophie, 'Note '.$case->value, 100 + $i, '2026-10-0'.($i + 1), 'repas', $case);
            if ($case->value === $status) {
                $expected[] = $expense->getId();
            }
        }
        $this->createExpense($tom, 'Note de Tom', 500, '2026-10-01', 'repas', ExpenseStatus::from($status));
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses?status='.$status);

        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertSame($expected, array_column($list, 'id'));
        self::assertSame([$status], array_values(array_unique(array_column($list, 'status'))));
    }

    public function testC9SansFiltreToutesLesNotesSontRenvoyees(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        foreach (ExpenseStatus::cases() as $case) {
            $this->createExpense($sophie, 'Note '.$case->value, 100, '2026-10-01', 'repas', $case);
        }
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses');

        self::assertResponseStatusCodeSame(200);
        self::assertEqualsCanonicalizing(
            ['draft', 'submitted', 'approved', 'rejected'],
            array_column($this->responseJson(), 'status'),
        );
    }

    public function testC9AucuneNoteDansLEtatFiltreRenvoieUneListeVide(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->createExpense($sophie, 'Brouillon');
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses?status=approved');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->responseJson());
    }

    public function testC9EtatInconnuRenvoie400AvecLeChampStatus(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->createExpense($sophie, 'Brouillon');
        $this->login($sophie);

        $this->client->request('GET', '/api/expenses?status=inconnu');

        $this->assertValidationError('status');
        self::assertArrayNotHasKey(0, $this->responseJson());
    }

    public function testAutorisationListeSansSessionRenvoie401(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->createExpense($sophie, 'Brouillon');

        $this->client->request('GET', '/api/expenses');

        $error = $this->assertErrorResponse(401);
        self::assertArrayNotHasKey('fields', $error);
        self::assertArrayNotHasKey(0, $this->responseJson());
    }
}
