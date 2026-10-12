<?php

namespace App\Tests\Controller;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PUT /api/policies/{slug} (admin) — spec 004 (C2, C3).
 * Corps : { maxAmountCents: int|null, receiptRequired: bool } ; réponse : la catégorie avec sa politique.
 */
final class PolicyControllerTest extends ApiTestCase
{
    public function testC2LAdminEnregistreLePlafondEtLeJustificatifDUneCategorie(): void
    {
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->jsonRequest('PUT', '/api/policies/hebergement', ['maxAmountCents' => 15000, 'receiptRequired' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['slug' => 'hebergement', 'label' => 'Hébergement', 'maxAmountCents' => 15000, 'receiptRequired' => true],
            $this->responseJson(),
        );
        $stored = $this->findCategory('hebergement');
        self::assertSame(15000, $stored->getMaxAmountCents());
        self::assertTrue($stored->isReceiptRequired());
        // Les autres catégories ne changent pas.
        self::assertNull($this->findCategory('repas')->getMaxAmountCents());
        self::assertFalse($this->findCategory('repas')->isReceiptRequired());
    }

    public function testC3UnPlafondNulSignifieAucunPlafond(): void
    {
        $this->setPolicy('materiel', 50000, true);
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->jsonRequest('PUT', '/api/policies/materiel', ['maxAmountCents' => null, 'receiptRequired' => false]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['slug' => 'materiel', 'label' => 'Matériel', 'maxAmountCents' => null, 'receiptRequired' => false],
            $this->responseJson(),
        );
        $stored = $this->findCategory('materiel');
        self::assertNull($stored->getMaxAmountCents());
        self::assertFalse($stored->isReceiptRequired());
    }

    public function testC3UnPlafondDe10000EurosEstAccepte(): void
    {
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->jsonRequest('PUT', '/api/policies/autre', ['maxAmountCents' => 1000000, 'receiptRequired' => false]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1000000, $this->responseJson()['maxAmountCents'] ?? null);
        self::assertSame(1000000, $this->findCategory('autre')->getMaxAmountCents());
    }

    public function testC3UnPlafondDeUnCentimeEstAccepte(): void
    {
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->jsonRequest('PUT', '/api/policies/autre', ['maxAmountCents' => 1, 'receiptRequired' => false]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->findCategory('autre')->getMaxAmountCents());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidMaxAmounts(): iterable
    {
        yield 'zéro' => [0];
        yield 'négatif' => [-500];
        yield 'au-delà de 10 000 €' => [1000001];
        yield 'flottant' => [50.5];
        yield 'chaîne' => ['5000'];
        yield 'booléen' => [true];
    }

    #[DataProvider('invalidMaxAmounts')]
    public function testC3PlafondInvalideRenvoie400SurLeChampSansModification(mixed $maxAmountCents): void
    {
        $this->setPolicy('repas', 5000);
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => $maxAmountCents, 'receiptRequired' => true]);

        $this->assertValidationError('maxAmountCents');
        $stored = $this->findCategory('repas');
        self::assertSame(5000, $stored->getMaxAmountCents());
        self::assertFalse($stored->isReceiptRequired());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidReceiptRequired(): iterable
    {
        yield 'absent' => [['maxAmountCents' => 5000]];
        yield 'null' => [['maxAmountCents' => 5000, 'receiptRequired' => null]];
        yield 'chaîne' => [['maxAmountCents' => 5000, 'receiptRequired' => 'oui']];
        yield 'entier' => [['maxAmountCents' => 5000, 'receiptRequired' => 1]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidReceiptRequired')]
    public function testC3JustificatifAbsentOuNonBooleenRenvoie400SurLeChamp(array $body): void
    {
        $this->setPolicy('repas', 3000);
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->jsonRequest('PUT', '/api/policies/repas', $body);

        $this->assertValidationError('receiptRequired');
        $stored = $this->findCategory('repas');
        self::assertSame(3000, $stored->getMaxAmountCents());
        self::assertFalse($stored->isReceiptRequired());
    }

    public function testC3CategorieInconnueRenvoie404(): void
    {
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->jsonRequest('PUT', '/api/policies/voyage-lune', ['maxAmountCents' => 5000, 'receiptRequired' => false]);

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        foreach (['repas', 'transport', 'hebergement', 'materiel', 'autre'] as $slug) {
            self::assertNull($this->findCategory($slug)->getMaxAmountCents());
        }
    }

    /**
     * @return iterable<string, array{UserRole}>
     */
    public static function nonAdminRoles(): iterable
    {
        yield 'manager' => [UserRole::Manager];
        yield 'salarié' => [UserRole::Employee];
    }

    #[DataProvider('nonAdminRoles')]
    public function testC2ManagerOuSalarieRenvoie403SansModification(UserRole $role): void
    {
        $this->setPolicy('repas', 5000);
        $user = $this->createUser('Non Admin', 'nonadmin@demo.test', $role);
        $this->login($user);

        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => 100000, 'receiptRequired' => true]);

        $error = $this->assertErrorResponse(403);
        self::assertArrayNotHasKey('fields', $error);
        $stored = $this->findCategory('repas');
        self::assertSame(5000, $stored->getMaxAmountCents());
        self::assertFalse($stored->isReceiptRequired());
    }

    public function testC2SansSessionRenvoie401SansModification(): void
    {
        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => 100000, 'receiptRequired' => true]);

        $this->assertErrorResponse(401);
        $stored = $this->findCategory('repas');
        self::assertNull($stored->getMaxAmountCents());
        self::assertFalse($stored->isReceiptRequired());
    }

    public function testC2LaNouvellePolitiqueSAppliqueAuxControlesSuivants(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel Lyon', 18000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['admin']);

        $this->jsonRequest('PUT', '/api/policies/hebergement', ['maxAmountCents' => 15000, 'receiptRequired' => true]);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame(['limitCents' => 15000, 'overLimitByCents' => 3000, 'receiptRequired' => true], $view['policy']);
    }

    public function testC2SupprimerLePlafondLeveLeSignalement(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Dîner', 9000, '2026-10-01', 'repas')->getId();
        $this->login($team['admin']);

        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => null, 'receiptRequired' => false]);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['limitCents' => null, 'overLimitByCents' => null, 'receiptRequired' => false], $this->responseJson()['policy'] ?? null);
    }

    public function testC3LaRouteNeModifiePasLesNotesExistantes(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Dîner', 9000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['admin']);

        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => 5000, 'receiptRequired' => false]);

        self::assertResponseStatusCodeSame(200);
        $stored = $this->findExpense($id);
        self::assertSame(9000, $stored?->getAmountCents());
        self::assertSame(ExpenseStatus::Submitted, $stored?->getStatus());
        self::assertNull($stored?->getLimitAtApprovalCents());
    }
}
