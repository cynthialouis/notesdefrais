<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/expenses (connecté) — spec 002.
 */
final class CreateExpenseTest extends ApiTestCase
{
    public function testC1CreationRenvoie201EtUnBrouillonAuNomDeLUtilisateur(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);
        $before = time();

        $this->jsonRequest('POST', '/api/expenses', [
            'label' => 'Déjeuner client',
            'amountCents' => 4590,
            'date' => '2026-10-01',
            'categorySlug' => 'repas',
        ]);

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame('Déjeuner client', $view['label']);
        self::assertSame(4590, $view['amountCents']);
        self::assertSame('2026-10-01', $view['date']);
        self::assertSame(['slug' => 'repas', 'label' => 'Repas'], $view['category']);
        self::assertSame('draft', $view['status']);
        self::assertNull($view['submittedAt']);
        $createdAt = (new \DateTimeImmutable($view['createdAt']))->getTimestamp();
        self::assertGreaterThanOrEqual($before - 2, $createdAt);
        self::assertLessThanOrEqual(time() + 2, $createdAt);

        $expense = $this->findExpense($view['id']);
        self::assertNotNull($expense);
        self::assertSame($sophie->getId(), $expense->getOwner()->getId());
        self::assertSame('Déjeuner client', $expense->getLabel());
        self::assertSame(4590, $expense->getAmountCents());
        self::assertSame('2026-10-01', $expense->getDate()->format('Y-m-d'));
        self::assertSame('repas', $expense->getCategory()->getSlug());
        self::assertSame(ExpenseStatus::Draft, $expense->getStatus());
        self::assertNull($expense->getSubmittedAt());
        self::assertSame(1, $this->countExpenses());
    }

    public function testC1LeStatutEtLeProprietaireEnvoyesParLeClientSontIgnoresOuRefuses(): void
    {
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload([
            'status' => 'approved',
            'ownerId' => $tom->getId(),
            'submittedAt' => '2026-10-01T10:00:00+02:00',
        ]));

        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [201, 400], 'Un champ en trop est soit ignoré, soit refusé.');
        if (201 === $status) {
            $view = $this->responseJson();
            self::assertSame('draft', $view['status']);
            self::assertNull($view['submittedAt']);
            $expense = $this->findExpense($view['id']);
            self::assertNotNull($expense);
            self::assertSame($sophie->getId(), $expense->getOwner()->getId());
            self::assertSame(ExpenseStatus::Draft, $expense->getStatus());
        } else {
            self::assertSame(0, $this->countExpenses());
        }
    }

    public function testC2LeMontantEnCentimesEstConserveExactement(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['amountCents' => 1250]));

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame(1250, $view['amountCents']);
        self::assertSame(1250, $this->findExpense($view['id'])?->getAmountCents());

        $this->client->request('GET', '/api/expenses/'.$view['id']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(1250, $this->responseJson()['amountCents']);
    }

    public function testC2DixMilleEurosSontAcceptes(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['amountCents' => 1_000_000]));

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame(1_000_000, $view['amountCents']);
        self::assertSame(1_000_000, $this->findExpense($view['id'])?->getAmountCents());
    }

    public function testC2UnCentimeEstAccepte(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['amountCents' => 1]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, $this->responseJson()['amountCents']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'montant 0' => [['amountCents' => 0], 'amountCents'];
        yield 'montant négatif' => [['amountCents' => -100], 'amountCents'];
        yield 'montant 10 000,01 €' => [['amountCents' => 1_000_001], 'amountCents'];
        yield 'montant à trois décimales (12,505 €)' => [['amountCents' => 1250.5], 'amountCents'];
        yield 'montant en chaîne' => [['amountCents' => '1250'], 'amountCents'];
        yield 'montant à null' => [['amountCents' => null], 'amountCents'];
        yield 'libellé d\'un caractère' => [['label' => 'a'], 'label'];
        yield 'libellé d\'un caractère entouré d\'espaces' => [['label' => '  a  '], 'label'];
        yield 'libellé fait uniquement d\'espaces' => [['label' => '     '], 'label'];
        yield 'libellé vide' => [['label' => ''], 'label'];
        yield 'libellé de 81 caractères' => [['label' => str_repeat('a', 81)], 'label'];
        yield 'libellé à null' => [['label' => null], 'label'];
        yield 'date au mauvais format' => [['date' => '01/10/2026'], 'date'];
        yield 'date inexistante' => [['date' => '2026-02-30'], 'date'];
        yield 'date non textuelle' => [['date' => 'pas-une-date'], 'date'];
        yield 'date vide' => [['date' => ''], 'date'];
        yield 'date à null' => [['date' => null], 'date'];
        yield 'catégorie inconnue' => [['categorySlug' => 'voyage-lune'], 'categorySlug'];
        yield 'catégorie vide' => [['categorySlug' => ''], 'categorySlug'];
        yield 'catégorie à null' => [['categorySlug' => null], 'categorySlug'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidFields')]
    public function testC3ChampInvalideRenvoie400AvecLeChampEnCause(array $overrides, string $field): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload($overrides));

        $this->assertValidationError($field);
        self::assertSame(0, $this->countExpenses());
    }

    public function testC3DateDeDemainEnEuropeParisRefusee(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['date' => $this->parisDate('+1 day')]));

        $this->assertValidationError('date');
        self::assertSame(0, $this->countExpenses());
    }

    public function testC3DateDuJourEnEuropeParisAcceptee(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);
        $today = $this->parisDate();

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['date' => $today]));

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame($today, $view['date']);
        self::assertSame($today, $this->findExpense($view['id'])?->getDate()->format('Y-m-d'));
    }

    public function testC3DateAncienneAcceptee(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['date' => '2019-01-15']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('2019-01-15', $this->responseJson()['date']);
    }

    public function testC3TousLesChampsInvalidesSontSignalesEnUneFois(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', [
            'label' => 'a',
            'amountCents' => 0,
            'date' => $this->parisDate('+1 day'),
            'categorySlug' => 'inconnue',
        ]);

        $this->assertValidationError('label', 'amountCents', 'date', 'categorySlug');
        self::assertSame(0, $this->countExpenses());
    }

    public function testC3CorpsVideSignaleLesQuatreChamps(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', []);

        $this->assertValidationError('label', 'amountCents', 'date', 'categorySlug');
        self::assertSame(0, $this->countExpenses());
    }

    public function testC3LibelleEntoureDEspacesEnregistreSansEux(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => '   Taxi gare   ']));

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame('Taxi gare', $view['label']);
        self::assertSame('Taxi gare', $this->findExpense($view['id'])?->getLabel());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validLabels(): iterable
    {
        yield '2 caractères' => ['ab'];
        yield '80 caractères' => [str_repeat('a', 80)];
        yield '80 caractères accentués' => [str_repeat('é', 80)];
        yield '80 caractères entourés d\'espaces' => ['  '.str_repeat('a', 80).'  '];
    }

    #[DataProvider('validLabels')]
    public function testC3LibelleAuxBornesAccepte(string $label): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => $label]));

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame(trim($label), $view['label']);
        self::assertSame(trim($label), $this->findExpense($view['id'])?->getLabel());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function categories(): iterable
    {
        yield 'repas' => ['repas', 'Repas'];
        yield 'transport' => ['transport', 'Transport'];
        yield 'hebergement' => ['hebergement', 'Hébergement'];
        yield 'materiel' => ['materiel', 'Matériel'];
        yield 'autre' => ['autre', 'Autre'];
    }

    #[DataProvider('categories')]
    public function testC10ChaqueCategorieDuServeurEstAcceptee(string $slug, string $label): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['categorySlug' => $slug]));

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        self::assertSame(['slug' => $slug, 'label' => $label], $view['category']);
        self::assertSame($slug, $this->findExpense($view['id'])?->getCategory()->getSlug());
    }

    public function testAutorisationCreationSansSessionRenvoie401EtNeCreeRien(): void
    {
        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload());

        $error = $this->assertErrorResponse(401);
        self::assertArrayNotHasKey('fields', $error);
        self::assertSame(0, $this->countExpenses());
    }

    public function testRelectureUnLibelleFaitDEspacesInsecablesEstRefuse(): void
    {
        $lea = $this->createUser('Léa', 'lea@demo.test', UserRole::Employee);
        $this->login($lea);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => "\u{00A0}\u{202F}\u{00A0}"]));

        $this->assertValidationError('label');
    }

    public function testRelectureLesEspacesUnicodeAutourDuLibelleSontRetires(): void
    {
        $lea = $this->createUser('Léa', 'lea@demo.test', UserRole::Employee);
        $this->login($lea);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => "\u{00A0}Taxi\u{2028}"]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Taxi', $this->responseJson()['label'] ?? null);
    }

    public function testRelectureLaLongueurDuLibelleSeCompteEnCaracteres(): void
    {
        $lea = $this->createUser('Léa', 'lea@demo.test', UserRole::Employee);
        $this->login($lea);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => str_repeat('🚕', 80)]));
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => str_repeat('🚕', 81)]));
        $this->assertValidationError('label');
    }
}
