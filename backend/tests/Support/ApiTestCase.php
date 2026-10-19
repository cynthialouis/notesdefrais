<?php

namespace App\Tests\Support;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\LeaveRequest;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use App\Enum\LeaveStatus;
use App\Enum\UserRole;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Base des tests de routes de l'API.
 *
 * La base de test est migrée par `make test` et remise à zéro entre les tests
 * par dama/doctrine-test-bundle (rollback de transaction) : chaque test crée
 * lui-même les utilisateurs dont il a besoin.
 *
 * Les justificatifs (spec 005) sont écrits sur disque, dans `var/receipts/test` : ce dossier n'est pas
 * remis à zéro par dama, il est vidé avant et après chaque test.
 */
abstract class ApiTestCase extends WebTestCase
{
    /** PNG 1×1 valide (69 octets). */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected KernelBrowser $client;

    /** @var list<string> fichiers temporaires créés pour les dépôts, effacés en fin de test */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearReceiptDir();
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $this->clearReceiptDir();
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
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
                'owner', 'decidedAt', 'decidedBy', 'rejectionReason', 'decidable', 'policy', 'receipt'],
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
        if (null !== $view['receipt']) {
            $this->assertReceiptShape($view['receipt']);
        }
    }

    /**
     * Signalement d'une note (spec 004) : { limitCents: int|null, overLimitByCents: int|null, receiptRequired: bool },
     * étendu par la spec 005 (C9) : receiptMissing: bool.
     */
    protected function assertPolicyShape(mixed $policy): void
    {
        self::assertIsArray($policy);
        self::assertEqualsCanonicalizing(['limitCents', 'overLimitByCents', 'receiptRequired', 'receiptMissing'], array_keys($policy));
        foreach (['limitCents', 'overLimitByCents'] as $key) {
            if (null !== $policy[$key]) {
                self::assertIsInt($policy[$key]);
            }
        }
        self::assertIsBool($policy['receiptRequired']);
        self::assertIsBool($policy['receiptMissing']);
    }

    /**
     * Justificatif d'une note (spec 005) : { name: nom d'origine, mimeType, sizeBytes }, les trois champs toujours présents.
     */
    protected function assertReceiptShape(mixed $receipt): void
    {
        self::assertIsArray($receipt);
        self::assertEqualsCanonicalizing(['name', 'mimeType', 'sizeBytes'], array_keys($receipt));
        self::assertIsString($receipt['name']);
        self::assertNotSame('', $receipt['name']);
        self::assertContains($receipt['mimeType'], ['image/jpeg', 'image/png', 'application/pdf']);
        self::assertIsInt($receipt['sizeBytes']);
        self::assertGreaterThan(0, $receipt['sizeBytes']);
    }

    // --- Justificatifs (spec 005) ----------------------------------------------------------

    /**
     * PNG valide, complété par des octets nuls jusqu'à `$size` octets si besoin (le type reste détecté d'après l'en-tête).
     */
    protected function pngBytes(int $size = 0): string
    {
        $png = (string) base64_decode(self::PNG_BASE64, true);

        return $size > \strlen($png) ? $png.str_repeat("\0", $size - \strlen($png)) : $png;
    }

    /** En-tête JPEG/JFIF suffisant pour la détection d'après le contenu. */
    protected function jpegBytes(): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00".str_repeat("\x00", 64)."\xFF\xD9";
    }

    /** PDF minimal. */
    protected function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    /** GIF 1×1 valide : une image, mais pas d'un format accepté. */
    protected function gifBytes(): string
    {
        return "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
    }

    /**
     * Fichier envoyé par le client : contenu écrit dans un fichier temporaire, nom et type annoncés par le navigateur.
     */
    protected function uploadedFile(string $content, string $originalName, ?string $clientMimeType = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt-test-');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $originalName, $clientMimeType, null, true);
    }

    /**
     * POST /api/expenses/{id}/receipt en multipart, champ fichier `file` (aucun fichier si null).
     */
    protected function uploadReceipt(int $expenseId, ?UploadedFile $file): void
    {
        $this->client->request('POST', '/api/expenses/'.$expenseId.'/receipt', [], null === $file ? [] : ['file' => $file], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
    }

    /**
     * Dépose un PNG valide sur une note et vérifie le succès (préparation d'un test).
     */
    protected function attachPng(int $expenseId, string $originalName = 'recu.png'): string
    {
        $content = $this->pngBytes();
        $this->uploadReceipt($expenseId, $this->uploadedFile($content, $originalName, 'image/png'));
        self::assertResponseStatusCodeSame(200, 'Préparation : le dépôt du justificatif doit réussir. '.$this->client->getResponse()->getContent());

        return $content;
    }

    /** Dossier de stockage des justificatifs en test : %kernel.project_dir%/var/receipts/test. */
    protected function receiptDir(): string
    {
        return \dirname(__DIR__, 2).'/var/receipts/test';
    }

    /**
     * Fichiers présents dans le dossier de stockage (sous-dossiers compris), chemins absolus triés.
     *
     * @return list<string>
     */
    protected function receiptFiles(): array
    {
        $dir = $this->receiptDir();
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function clearReceiptDir(): void
    {
        $dir = $this->receiptDir();
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    }

    /**
     * Fait passer une note à un état donné directement en base (préparation d'un test, sans le Workflow).
     */
    protected function forceStatus(int $expenseId, ExpenseStatus $status): void
    {
        $expense = $this->findExpense($expenseId);
        self::assertNotNull($expense);
        $expense->setStatus($status);
        if (ExpenseStatus::Draft !== $status && null === $expense->getSubmittedAt()) {
            $expense->setSubmittedAt(new \DateTimeImmutable('2026-10-02T09:00:00+02:00'));
        }
        $this->entityManager()->flush();
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

    // --- Tableau de bord (spec 006) -------------------------------------------------------

    /**
     * Crée une note validée (status approved) avec sa date de soumission, sa date de validation et son décideur,
     * directement en base (préparation d'un test, sans le Workflow).
     */
    protected function createApprovedExpense(
        User $owner,
        User $decider,
        string $label = 'Note validée',
        int $amountCents = 1000,
        string $date = '2026-10-01',
        string $categorySlug = 'repas',
        string $decidedAt = '2026-10-05T10:00:00+02:00',
    ): Expense {
        $expense = $this->createExpense($owner, $label, $amountCents, $date, $categorySlug, ExpenseStatus::Approved, '2026-10-02T09:00:00+02:00');
        $managedDecider = $this->entityManager()->find(User::class, $decider->getId());
        self::assertInstanceOf(User::class, $managedDecider);
        $expense->setDecidedAt(new \DateTimeImmutable($decidedAt));
        $expense->setDecidedBy($managedDecider);
        $this->entityManager()->flush();

        return $expense;
    }

    /**
     * Corps brut de la dernière réponse, y compris pour une StreamedResponse (contenu capturé par le client).
     */
    protected function responseBody(): string
    {
        return (string) $this->client->getInternalResponse()->getContent();
    }

    /**
     * Découpe un CSV (séparateur « ; », fins de ligne \r\n, guillemets RFC 4180) en lignes de champs.
     * Le BOM éventuel doit avoir été retiré. Une fin de fichier \r\n ne produit pas de ligne vide.
     *
     * @return list<list<string>>
     */
    protected function parseCsv(string $csv): array
    {
        $rows = [];
        $row = [];
        $field = '';
        $quoted = false;
        $length = \strlen($csv);
        for ($i = 0; $i < $length; ++$i) {
            $char = $csv[$i];
            if ($quoted) {
                if ('"' === $char) {
                    if ($i + 1 < $length && '"' === $csv[$i + 1]) {
                        $field .= '"';
                        ++$i;
                    } else {
                        $quoted = false;
                    }
                } else {
                    $field .= $char;
                }
                continue;
            }
            if ('"' === $char && '' === $field) {
                $quoted = true;
            } elseif (';' === $char) {
                $row[] = $field;
                $field = '';
            } elseif ("\r" === $char && $i + 1 < $length && "\n" === $csv[$i + 1]) {
                $row[] = $field;
                $rows[] = $row;
                $row = [];
                $field = '';
                ++$i;
            } else {
                $field .= $char;
            }
        }
        if ('' !== $field || [] !== $row) {
            $row[] = $field;
            $rows[] = $row;
        }

        return $rows;
    }

    // --- Congés (spec 007) ---------------------------------------------------------------

    /**
     * Crée et enregistre une demande de congé directement en base (préparation d'un test, sans le Workflow).
     * Seul endroit des tests qui appelle les setters de LeaveRequest (noms supposés, non fixés par le plan) :
     * setRequester, setStartDate, setEndDate, setStatus, setDecidedAt, setDecidedBy, setRefusalReason,
     * setCancelledAt, setCancelledBy, setCancellationReason.
     * Les personnes sont rechargées depuis l'EntityManager courant : le helper reste utilisable après une requête.
     */
    protected function createLeave(
        User $requester,
        string $startDate,
        string $endDate,
        LeaveStatus $status = LeaveStatus::Pending,
        ?User $decidedBy = null,
        ?string $refusalReason = null,
        ?User $cancelledBy = null,
        ?string $cancellationReason = null,
    ): LeaveRequest {
        $em = $this->entityManager();
        $managedRequester = $em->find(User::class, $requester->getId());
        self::assertInstanceOf(User::class, $managedRequester);

        $leave = new LeaveRequest();
        $leave->setRequester($managedRequester);
        $leave->setStartDate(new \DateTimeImmutable($startDate));
        $leave->setEndDate(new \DateTimeImmutable($endDate));
        $leave->setStatus($status);
        if (null !== $decidedBy) {
            $leave->setDecidedBy($em->find(User::class, $decidedBy->getId()));
            $leave->setDecidedAt(new \DateTimeImmutable('2026-10-01T10:00:00+02:00'));
        }
        if (null !== $refusalReason) {
            $leave->setRefusalReason($refusalReason);
        }
        if (null !== $cancelledBy) {
            $leave->setCancelledBy($em->find(User::class, $cancelledBy->getId()));
            $leave->setCancelledAt(new \DateTimeImmutable('2026-10-02T10:00:00+02:00'));
        }
        if (null !== $cancellationReason) {
            $leave->setCancellationReason($cancellationReason);
        }

        $em->persist($leave);
        $em->flush();

        return $leave;
    }

    /**
     * Crée une demande dans l'état voulu, avec les traces cohérentes de cet état :
     * décision par le responsable du demandeur (acceptée, refusée), annulation par `$admin` (annulée).
     */
    protected function createLeaveInStatus(User $requester, string $startDate, string $endDate, LeaveStatus $status, ?User $admin = null): LeaveRequest
    {
        $responsible = $requester->getManager();

        return match ($status) {
            LeaveStatus::Pending, LeaveStatus::Withdrawn => $this->createLeave($requester, $startDate, $endDate, $status),
            LeaveStatus::Accepted => $this->createLeave($requester, $startDate, $endDate, $status, decidedBy: $responsible),
            LeaveStatus::Refused => $this->createLeave($requester, $startDate, $endDate, $status, decidedBy: $responsible, refusalReason: 'Motif de refus secret'),
            LeaveStatus::Cancelled => $this->createLeave($requester, $startDate, $endDate, $status, cancelledBy: $admin ?? $responsible, cancellationReason: 'Motif d\'annulation secret'),
        };
    }

    /**
     * Relit une demande de congé en base, sans passer par le cache de l'EntityManager.
     */
    protected function findLeave(int $id): ?LeaveRequest
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->find(LeaveRequest::class, $id);
    }

    protected function countLeaves(): int
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->getRepository(LeaveRequest::class)->count([]);
    }

    /**
     * Vérifie le format de sortie d'une demande de congé (LeaveRequestView, spec 007, data-model.md) :
     * champs nullables toujours présents, booléens de ce que le lecteur peut faire.
     *
     * @param array<mixed> $view
     */
    protected function assertLeaveView(mixed $view): void
    {
        self::assertIsArray($view);
        self::assertEqualsCanonicalizing(
            ['id', 'requester', 'startDate', 'endDate', 'status', 'createdAt', 'decidedAt', 'decidedBy', 'refusalReason',
                'cancelledAt', 'cancelledBy', 'cancellationReason', 'decidable', 'withdrawable', 'cancellable'],
            array_keys($view),
        );
        self::assertIsInt($view['id']);
        $this->assertPersonRef($view['requester']);
        foreach (['startDate', 'endDate'] as $key) {
            self::assertIsString($view[$key]);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $view[$key]);
        }
        self::assertContains($view['status'], ['pending', 'accepted', 'refused', 'withdrawn', 'cancelled']);
        self::assertIsString($view['createdAt']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $view['createdAt']);
        foreach (['decidedAt', 'cancelledAt'] as $key) {
            if (null !== $view[$key]) {
                self::assertIsString($view[$key]);
                self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $view[$key]);
            }
        }
        foreach (['decidedBy', 'cancelledBy'] as $key) {
            if (null !== $view[$key]) {
                $this->assertPersonRef($view[$key]);
            }
        }
        foreach (['refusalReason', 'cancellationReason'] as $key) {
            if (null !== $view[$key]) {
                self::assertIsString($view[$key]);
            }
        }
        self::assertIsBool($view['decidable']);
        self::assertIsBool($view['withdrawable']);
        self::assertIsBool($view['cancellable']);
    }

    /**
     * Liste de demandes renvoyée par une route : code 200, tableau JSON de LeaveRequestView, renvoyé tel quel.
     *
     * @return list<array<mixed>>
     */
    protected function leaveList(): array
    {
        self::assertResponseStatusCodeSame(200);
        $data = $this->responseJson();
        self::assertTrue(array_is_list($data), 'La réponse doit être un tableau JSON.');
        foreach ($data as $view) {
            $this->assertLeaveView($view);
        }

        /** @var list<array<mixed>> $data */
        return $data;
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
