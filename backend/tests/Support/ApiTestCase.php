<?php

namespace App\Tests\Support;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests de routes de l'API.
 *
 * La base de test est migrée par `make test` et remise à zéro entre les tests
 * par dama/doctrine-test-bundle (rollback de transaction) : chaque test crée
 * lui-même les utilisateurs dont il a besoin.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    /**
     * Crée et enregistre un utilisateur (entité prévue par la spec 001 : name, email, role, manager).
     */
    protected function createUser(string $name, string $email, UserRole $role, ?User $manager = null): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setRole($role);
        $user->setManager($manager);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Envoie une requête JSON. `$body` est encodé en JSON s'il n'est pas déjà une chaîne.
     */
    protected function jsonRequest(string $method, string $uri, mixed $body = null): void
    {
        $content = null;
        if (null !== $body) {
            $content = \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR);
        }

        $this->client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $content);
    }

    protected function login(User $user): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['userId' => $user->getId()]);
    }

    /**
     * @return array<mixed>
     */
    protected function responseJson(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        self::assertJson($content, 'La réponse doit être du JSON : '.$content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * Vérifie le format d'erreur unique `{ error: { code, message, fields? } }` et le renvoie.
     *
     * @return array<mixed>
     */
    protected function assertErrorResponse(int $status): array
    {
        self::assertResponseStatusCodeSame($status);
        $data = $this->responseJson();

        self::assertArrayHasKey('error', $data);
        self::assertIsArray($data['error']);
        self::assertArrayHasKey('code', $data['error']);
        self::assertIsString($data['error']['code']);
        self::assertNotSame('', $data['error']['code']);
        self::assertArrayHasKey('message', $data['error']);
        self::assertIsString($data['error']['message']);
        self::assertNotSame('', $data['error']['message']);

        return $data['error'];
    }

    /**
     * Vérifie qu'aucune session n'est ouverte : GET /api/me répond 401.
     */
    protected function assertNotAuthenticated(): void
    {
        $this->client->request('GET', '/api/me');
        $this->assertErrorResponse(401);
    }

    /**
     * Vérifie que GET /api/me renvoie l'utilisateur attendu.
     *
     * @return array<mixed>
     */
    protected function assertAuthenticatedAs(User $user): array
    {
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(200);
        $me = $this->responseJson();
        self::assertSame($user->getId(), $me['id'] ?? null);
        self::assertSame($user->getEmail(), $me['email'] ?? null);

        return $me;
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Catégorie de référence insérée par la migration (spec 002) : repas, transport, hebergement, materiel, autre.
     */
    protected function category(string $slug): Category
    {
        $category = static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Category::class, $category, "La catégorie '$slug' doit exister en base (insérée par la migration).");

        return $category;
    }

    /**
     * Crée et enregistre une note directement en base (entité prévue par la spec 002).
     * Le propriétaire est rechargé depuis l'EntityManager courant : le helper reste utilisable après une requête.
     */
    protected function createExpense(
        User $owner,
        string $label = 'Déjeuner client',
        int $amountCents = 1250,
        string $date = '2026-10-01',
        string $categorySlug = 'repas',
        ExpenseStatus $status = ExpenseStatus::Draft,
        ?string $submittedAt = null,
    ): Expense {
        $em = $this->entityManager();
        $managedOwner = $em->find(User::class, $owner->getId());
        self::assertInstanceOf(User::class, $managedOwner);

        $expense = new Expense();
        $expense->setOwner($managedOwner);
        $expense->setCategory($this->category($categorySlug));
        $expense->setLabel($label);
        $expense->setAmountCents($amountCents);
        $expense->setDate(new \DateTimeImmutable($date));
        $expense->setStatus($status);
        if (null !== $submittedAt) {
            $expense->setSubmittedAt(new \DateTimeImmutable($submittedAt));
        }

        $em->persist($expense);
        $em->flush();

        return $expense;
    }

    /**
     * Relit une note en base, sans passer par le cache de l'EntityManager.
     */
    protected function findExpense(int $id): ?Expense
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->find(Expense::class, $id);
    }

    protected function countExpenses(): int
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->getRepository(Expense::class)->count([]);
    }

    /**
     * Corps valide pour POST /api/expenses et PATCH /api/expenses/{id}.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Taxi gare',
            'amountCents' => 2350,
            'date' => '2026-10-02',
            'categorySlug' => 'transport',
        ], $overrides);
    }

    /**
     * Date du jour en Europe/Paris, décalée de `$modifier` (ex. '+1 day'), au format Y-m-d.
     */
    protected function parisDate(string $modifier = 'now'): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->modify($modifier)->format('Y-m-d');
    }

    /**
     * Vérifie le format de sortie d'une note (ExpenseView), étendu par la spec 003
     * (owner, decidedAt, decidedBy, rejectionReason, decidable) puis par la spec 004 (policy).
     *
     * @param array<mixed> $view
     */
    protected function assertExpenseView(array $view): void
    {
        self::assertEqualsCanonicalizing(
            ['id', 'label', 'amountCents', 'date', 'category', 'status', 'createdAt', 'submittedAt', 'editable',
                'owner', 'decidedAt', 'decidedBy', 'rejectionReason', 'decidable', 'policy'],
            array_keys($view),
        );
        self::assertIsInt($view['id']);
        self::assertIsString($view['label']);
        self::assertIsInt($view['amountCents']);
        self::assertIsString($view['date']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $view['date']);
        self::assertIsArray($view['category']);
        self::assertEqualsCanonicalizing(['slug', 'label'], array_keys($view['category']));
        self::assertContains($view['status'], ['draft', 'submitted', 'approved', 'rejected']);
        self::assertIsBool($view['editable']);
        self::assertIsBool($view['decidable']);
        self::assertIsString($view['createdAt']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $view['createdAt']);
        foreach (['submittedAt', 'decidedAt'] as $key) {
            if (null !== $view[$key]) {
                self::assertIsString($view[$key]);
                self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $view[$key]);
            }
        }
        $this->assertPersonRef($view['owner']);
        if (null !== $view['decidedBy']) {
            $this->assertPersonRef($view['decidedBy']);
        }
        if (null !== $view['rejectionReason']) {
            self::assertIsString($view['rejectionReason']);
        }
        $this->assertPolicyShape($view['policy']);
    }

    /**
     * Signalement d'une note (spec 004) : { limitCents: int|null, overLimitByCents: int|null, receiptRequired: bool }.
     */
    protected function assertPolicyShape(mixed $policy): void
    {
        self::assertIsArray($policy);
        self::assertEqualsCanonicalizing(['limitCents', 'overLimitByCents', 'receiptRequired'], array_keys($policy));
        foreach (['limitCents', 'overLimitByCents'] as $key) {
            if (null !== $policy[$key]) {
                self::assertIsInt($policy[$key]);
            }
        }
        self::assertIsBool($policy['receiptRequired']);
    }

    /**
     * Règle la politique d'une catégorie directement en base (spec 004 : null = aucun plafond).
     */
    protected function setPolicy(string $slug, ?int $maxAmountCents, bool $receiptRequired = false): void
    {
        $category = $this->category($slug);
        $category->setMaxAmountCents($maxAmountCents);
        $category->setReceiptRequired($receiptRequired);
        $this->entityManager()->flush();
    }

    /**
     * Relit une catégorie en base, sans passer par le cache de l'EntityManager.
     */
    protected function findCategory(string $slug): Category
    {
        $this->entityManager()->clear();

        return $this->category($slug);
    }

    /**
     * Référence à une personne dans une sortie de l'API : { id, name } (spec 003).
     */
    protected function assertPersonRef(mixed $ref): void
    {
        self::assertIsArray($ref);
        self::assertEqualsCanonicalizing(['id', 'name'], array_keys($ref));
        self::assertIsInt($ref['id']);
        self::assertIsString($ref['name']);
    }

    /**
     * Équipe type de la spec 003 : admin (sans responsable), managers rattachés à l'admin,
     * salariés rattachés à leur manager.
     *
     * @return array{admin: User, marc: User, julie: User, sophie: User, tom: User, ines: User}
     */
    protected function createTeam(): array
    {
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager, $admin);
        $julie = $this->createUser('Julie Manager', 'julie@demo.test', UserRole::Manager, $admin);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $tom = $this->createUser('Tom Salarié', 'tom@demo.test', UserRole::Employee, $marc);
        $ines = $this->createUser('Inès Salariée', 'ines@demo.test', UserRole::Employee, $julie);

        return ['admin' => $admin, 'marc' => $marc, 'julie' => $julie, 'sophie' => $sophie, 'tom' => $tom, 'ines' => $ines];
    }

    /**
     * Vérifie qu'un horodatage ISO 8601 tombe dans la fenêtre [$before, $after] (à 2 s près).
     */
    protected function assertIsoBetween(mixed $iso, int $before, int $after): void
    {
        self::assertIsString($iso);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $iso);
        $ts = (new \DateTimeImmutable($iso))->getTimestamp();
        self::assertGreaterThanOrEqual($before - 2, $ts);
        self::assertLessThanOrEqual($after + 2, $ts);
    }

    /**
     * Vérifie une erreur de validation 400 portant au moins sur les champs donnés, et renvoie les erreurs par champ.
     *
     * @return array<mixed>
     */
    protected function assertValidationError(string ...$fields): array
    {
        $error = $this->assertErrorResponse(400);
        self::assertArrayHasKey('fields', $error);
        self::assertIsArray($error['fields']);
        foreach ($fields as $field) {
            self::assertArrayHasKey($field, $error['fields'], "Le champ '$field' doit être signalé.");
            self::assertIsString($error['fields'][$field]);
            self::assertNotSame('', $error['fields'][$field]);
        }

        return $error['fields'];
    }
}
