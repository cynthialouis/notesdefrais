<?php

namespace App\Tests\Controller\Expense;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * POST /api/expenses/{id}/receipt (propriétaire, multipart, champ `file`) — spec 005 (C1, C2, C3, C6, C7, C12).
 * Réponse : 200 avec l'ExpenseView mise à jour (`receipt` : { name, mimeType, sizeBytes }).
 */
final class UploadReceiptTest extends ApiTestCase
{
    private const MAX_BYTES = 5_000_000;

    // --- C1 : dépôt d'un JPEG, d'un PNG ou d'un PDF sur une note modifiable ----------------

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function acceptedFiles(): iterable
    {
        yield 'PNG' => ['png', 'recu-hotel.png', 'image/png'];
        yield 'JPEG' => ['jpeg', 'ticket-taxi.jpg', 'image/jpeg'];
        yield 'PDF' => ['pdf', 'facture.pdf', 'application/pdf'];
    }

    #[DataProvider('acceptedFiles')]
    public function testC1LeProprietaireJointUnJustificatifAUnBrouillon(string $kind, string $name, string $mimeType): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel Lyon', 12900, '2026-10-01', 'hebergement')->getId();
        $content = $this->bytes($kind);
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile($content, $name, $mimeType));

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($id, $view['id']);
        self::assertSame('draft', $view['status']);
        self::assertSame(['name' => $name, 'mimeType' => $mimeType, 'sizeBytes' => \strlen($content)], $view['receipt']);

        $receipt = $this->findExpense($id)?->getReceipt();
        self::assertNotNull($receipt);
        self::assertSame($name, $receipt->getOriginalName());
        self::assertSame($mimeType, $receipt->getMimeType());
        self::assertSame(\strlen($content), $receipt->getSizeBytes());

        $files = $this->receiptFiles();
        self::assertCount(1, $files);
        self::assertSame($content, file_get_contents($files[0]));
    }

    public function testC1LeProprietaireJointUnJustificatifAUneNoteRejetee(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel Lyon', 12900, '2026-10-01', 'hebergement', ExpenseStatus::Rejected, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile($this->pdfBytes(), 'facture.pdf', 'application/pdf'));

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame('rejected', $view['status']);
        self::assertSame('facture.pdf', $view['receipt']['name'] ?? null);
        self::assertSame(ExpenseStatus::Rejected, $this->findExpense($id)?->getStatus());
        self::assertSame('facture.pdf', $this->findExpense($id)?->getReceipt()?->getOriginalName());
        self::assertCount(1, $this->receiptFiles());
    }

    public function testC1UneNoteSansJustificatifRenvoieReceiptNull(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertNull($view['receipt']);
        self::assertNull($this->findExpense($id)?->getReceipt());
    }

    public function testC1LeJustificatifNeConcerneQueSaNote(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $other = (int) $this->createExpense($team['sophie'], 'Autre hôtel', 9900, '2026-10-02', 'hebergement')->getId();
        $this->login($team['sophie']);

        $this->attachPng($id);

        self::assertNotNull($this->findExpense($id)?->getReceipt());
        self::assertNull($this->findExpense($other)?->getReceipt());
        $this->client->request('GET', '/api/expenses');
        $byId = array_column($this->responseJson(), null, 'id');
        self::assertSame('recu.png', $byId[$id]['receipt']['name'] ?? null);
        self::assertArrayHasKey('receipt', $byId[$other]);
        self::assertNull($byId[$other]['receipt']);
    }

    // --- C2 : type détecté d'après le contenu ----------------------------------------------

    public function testC2UnPngNommePdfEstAccepteCommeImagePng(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $content = $this->pngBytes();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile($content, 'facture.pdf', 'application/pdf'));

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame(['name' => 'facture.pdf', 'mimeType' => 'image/png', 'sizeBytes' => \strlen($content)], $view['receipt']);
        self::assertSame('image/png', $this->findExpense($id)?->getReceipt()?->getMimeType());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedTypes(): iterable
    {
        yield 'texte nommé .jpg' => ["Ceci n'est pas une image.\n", 'recu.jpg', 'image/jpeg'];
        yield 'texte nommé .pdf' => ["%Faux PDF\nbonjour\n", 'facture.pdf', 'application/pdf'];
        yield 'GIF nommé .png' => ['gif', 'recu.png', 'image/png'];
        yield 'GIF annoncé comme GIF' => ['gif', 'anim.gif', 'image/gif'];
    }

    #[DataProvider('refusedTypes')]
    public function testC2UnContenuQuiNEstNiJpegNiPngNiPdfEstRefuseAvecLesFormatsAcceptes(string $content, string $name, string $clientMime): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile('gif' === $content ? $this->gifBytes() : $content, $name, $clientMime));

        $fields = $this->assertValidationError('file');
        self::assertStringContainsString('JPEG, PNG ou PDF', $fields['file']);
        $this->assertNothingStored($id);
    }

    // --- C3 : taille ≤ 5 Mo (5 000 000 octets), fichier vide refusé ------------------------

    public function testC3UnFichierDe5MoExactementEstAccepte(): void
    {
        $this->assertUploadLimitAllowsTheTest();
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile($this->pngBytes(self::MAX_BYTES), 'scan.png', 'image/png'));

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        self::assertSame(self::MAX_BYTES, $view['receipt']['sizeBytes'] ?? null);
        self::assertSame(self::MAX_BYTES, $this->findExpense($id)?->getReceipt()?->getSizeBytes());
        $files = $this->receiptFiles();
        self::assertCount(1, $files);
        self::assertSame(self::MAX_BYTES, filesize($files[0]));
    }

    public function testC3UnFichierDe5MoPlusUnOctetEstRefuse(): void
    {
        $this->assertUploadLimitAllowsTheTest();
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile($this->pngBytes(self::MAX_BYTES + 1), 'scan.png', 'image/png'));

        $fields = $this->assertValidationError('file');
        self::assertStringContainsString('5 Mo', $fields['file']);
        $this->assertNothingStored($id);
    }

    public function testC3UnFichierVideEstRefuse(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile('', 'vide.pdf', 'application/pdf'));

        $fields = $this->assertValidationError('file');
        self::assertStringContainsString('vide', $fields['file']);
        $this->assertNothingStored($id);
    }

    public function testCasLimiteAucunFichierDansLaRequeteRenvoie400(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, null);

        $this->assertErrorResponse(400);
        $this->assertNothingStored($id);
    }

    public function testCasLimiteUnFichierSousUnAutreChampQueFileRenvoie400(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/expenses/'.$id.'/receipt', [], ['receipt' => $this->uploadedFile($this->pngBytes(), 'recu.png', 'image/png')], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertErrorResponse(400);
        $this->assertNothingStored($id);
    }

    public function testC3UnRefusNeRemplacePasLeJustificatifExistant(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $content = $this->attachPng($id, 'premier.png');
        $before = $this->receiptFiles();

        $this->uploadReceipt($id, $this->uploadedFile('', 'vide.png', 'image/png'));

        $this->assertValidationError('file');
        self::assertSame('premier.png', $this->findExpense($id)?->getReceipt()?->getOriginalName());
        self::assertSame($before, $this->receiptFiles());
        self::assertSame($content, file_get_contents($before[0]));
    }

    // --- C6 : remplacement tant que la note est modifiable ---------------------------------

    /**
     * @return iterable<string, array{ExpenseStatus}>
     */
    public static function editableStatuses(): iterable
    {
        yield 'brouillon' => [ExpenseStatus::Draft];
        yield 'rejetée' => [ExpenseStatus::Rejected];
    }

    #[DataProvider('editableStatuses')]
    public function testC6UnNouveauDepotRemplaceLeJustificatifEtEffaceLAncienFichier(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id, 'ancien.png');
        $oldFiles = $this->receiptFiles();
        self::assertCount(1, $oldFiles);
        $oldStorageName = $this->findExpense($id)?->getReceipt()?->getStorageName();
        $this->forceStatus($id, $status);

        $pdf = $this->pdfBytes();
        $this->uploadReceipt($id, $this->uploadedFile($pdf, 'nouveau.pdf', 'application/pdf'));

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame(['name' => 'nouveau.pdf', 'mimeType' => 'application/pdf', 'sizeBytes' => \strlen($pdf)], $view['receipt']);

        $receipt = $this->findExpense($id)?->getReceipt();
        self::assertNotNull($receipt);
        self::assertSame('nouveau.pdf', $receipt->getOriginalName());
        self::assertSame('application/pdf', $receipt->getMimeType());
        self::assertNotSame($oldStorageName, $receipt->getStorageName());

        // Un seul justificatif par note (C1) : l'ancien fichier est effacé du disque.
        $files = $this->receiptFiles();
        self::assertCount(1, $files);
        self::assertFileDoesNotExist($oldFiles[0]);
        self::assertSame($pdf, file_get_contents($files[0]));
    }

    // --- C7 : note non modifiable (soumise, validée) → 409, rien ne change ----------------

    /**
     * @return iterable<string, array{ExpenseStatus}>
     */
    public static function lockedStatuses(): iterable
    {
        yield 'soumise' => [ExpenseStatus::Submitted];
        yield 'validée' => [ExpenseStatus::Approved];
    }

    #[DataProvider('lockedStatuses')]
    public function testC7DeposerSurUneNoteNonModifiableRenvoie409SansRienEnregistrer(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Déjeuner', 1250, '2026-10-01', 'repas', $status, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile($this->pngBytes(), 'recu.png', 'image/png'));

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertNothingStored($id);
        self::assertSame($status, $this->findExpense($id)?->getStatus());
    }

    #[DataProvider('lockedStatuses')]
    public function testC7RemplacerLeJustificatifDUneNoteNonModifiableRenvoie409(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $content = $this->attachPng($id, 'origine.png');
        $before = $this->receiptFiles();
        $storageName = $this->findExpense($id)?->getReceipt()?->getStorageName();
        $this->forceStatus($id, $status);

        $this->uploadReceipt($id, $this->uploadedFile($this->pdfBytes(), 'remplacement.pdf', 'application/pdf'));

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $receipt = $this->findExpense($id)?->getReceipt();
        self::assertNotNull($receipt);
        self::assertSame('origine.png', $receipt->getOriginalName());
        self::assertSame('image/png', $receipt->getMimeType());
        self::assertSame($storageName, $receipt->getStorageName());
        self::assertSame($before, $this->receiptFiles());
        self::assertSame($content, file_get_contents($before[0]));
    }

    // --- Autorisations : propriétaire seulement ; 404 pour tout autre ; 401 sans session ----

    public function testAutorisationDepotSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();

        $this->uploadReceipt($id, $this->uploadedFile($this->pngBytes(), 'recu.png', 'image/png'));

        $this->assertErrorResponse(401);
        $this->assertNothingStored($id);
    }

    public function testAutorisationLeResponsableNePeutPasDeposerSurLaNoteDUnMembre404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement', ExpenseStatus::Rejected, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);

        $this->uploadReceipt($id, $this->uploadedFile($this->pngBytes(), 'recu.png', 'image/png'));

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        $this->assertNothingStored($id);
    }

    public function testAutorisationUnAutreSalarieRecoit404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['tom']);

        $this->uploadReceipt($id, $this->uploadedFile($this->pngBytes(), 'recu.png', 'image/png'));

        $this->assertErrorResponse(404);
        $this->assertNothingStored($id);
    }

    public function testAutorisationUnAutreNePeutPasRemplacerLeJustificatif404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id, 'origine.png');
        $before = $this->receiptFiles();
        $this->login($team['admin']);

        $this->uploadReceipt($id, $this->uploadedFile($this->pdfBytes(), 'pirate.pdf', 'application/pdf'));

        $this->assertErrorResponse(404);
        self::assertSame('origine.png', $this->findExpense($id)?->getReceipt()?->getOriginalName());
        self::assertSame($before, $this->receiptFiles());
    }

    public function testAutorisationDepotSurUneNoteInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id + 1000, $this->uploadedFile($this->pngBytes(), 'recu.png', 'image/png'));

        $this->assertErrorResponse(404);
        $this->assertNothingStored($id);
    }

    // --- C12 : stockage hors de public/, sous un nom généré par le serveur ------------------

    public function testC12LeFichierEstStockeHorsDuPublicSousUnNomGenere(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->attachPng($id, 'recu-hotel.png');

        $receipt = $this->findExpense($id)?->getReceipt();
        self::assertNotNull($receipt);
        $storageName = $receipt->getStorageName();
        self::assertNotSame('', $storageName);
        self::assertStringNotContainsString('recu-hotel', $storageName);
        $files = $this->receiptFiles();
        self::assertCount(1, $files);
        self::assertStringStartsWith($this->receiptDir().'/', $files[0]);
        self::assertStringEndsWith($storageName, $files[0]);
        self::assertStringNotContainsString('/public/', $files[0]);
    }

    public function testC12DeuxDepotsDuMemeNomSurDeuxNotesNeSEcrasentPas(): void
    {
        $team = $this->createTeam();
        $first = (int) $this->createExpense($team['sophie'])->getId();
        $second = (int) $this->createExpense($team['sophie'], 'Autre')->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($first, $this->uploadedFile($this->pngBytes(), 'recu.png', 'image/png'));
        self::assertResponseStatusCodeSame(200);
        $this->uploadReceipt($second, $this->uploadedFile($this->pdfBytes(), 'recu.png', 'application/pdf'));
        self::assertResponseStatusCodeSame(200);

        $a = $this->findExpense($first)?->getReceipt()?->getStorageName();
        $b = $this->findExpense($second)?->getReceipt()?->getStorageName();
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertNotSame($a, $b);
        self::assertCount(2, $this->receiptFiles());
    }

    /**
     * Nom envoyé, nom conservé pour l'affichage : le chemin est retiré à la réception (UploadedFile),
     * spec amendée le 13/10.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function hostileNames(): iterable
    {
        yield 'remontée de dossier' => ['../../public/evil.png', 'evil.png'];
        yield 'remontée profonde' => ['../../../../../../tmp/evil-receipt.png', 'evil-receipt.png'];
        yield 'caractères spéciaux' => ['Reçu « hôtel » #1 & co ;$(rm).png', 'Reçu « hôtel » #1 & co ;$(rm).png'];
        yield 'séparateur Windows' => ['..\\..\\public\\evil.png', 'evil.png'];
    }

    #[DataProvider('hostileNames')]
    public function testCasLimiteUnNomDOrigineHostileEstConservePourLAffichageSeulement(string $originalName, string $displayedName): void
    {
        $projectDir = \dirname(__DIR__, 3);
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->uploadReceipt($id, $this->uploadedFile($this->pngBytes(), $originalName, 'image/png'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame($displayedName, $this->responseJson()['receipt']['name'] ?? null);
        $receipt = $this->findExpense($id)?->getReceipt();
        self::assertNotNull($receipt);
        self::assertSame($displayedName, $receipt->getOriginalName());
        $storageName = $receipt->getStorageName();
        self::assertNotSame($originalName, $storageName);
        self::assertStringNotContainsString('..', $storageName);
        self::assertStringNotContainsString('evil', $storageName);
        self::assertStringNotContainsString('Reçu', $storageName);

        // Aucun fichier hors du dossier de stockage.
        $files = $this->receiptFiles();
        self::assertCount(1, $files);
        self::assertStringStartsWith($this->receiptDir().'/', $files[0]);
        self::assertFileDoesNotExist($projectDir.'/public/evil.png');
        self::assertFileDoesNotExist($projectDir.'/var/evil.png');
        self::assertFileDoesNotExist($projectDir.'/var/receipts/evil.png');
        self::assertFileDoesNotExist('/tmp/evil-receipt.png');
        // Aucun fichier posé directement dans var/receipts (seuls les dossiers par environnement y vivent).
        foreach (array_diff((array) scandir($projectDir.'/var/receipts'), ['.', '..']) as $entry) {
            self::assertDirectoryExists($projectDir.'/var/receipts/'.$entry);
        }
    }

    // --- Outils -------------------------------------------------------------------------------

    private function bytes(string $kind): string
    {
        return match ($kind) {
            'png' => $this->pngBytes(),
            'jpeg' => $this->jpegBytes(),
            'pdf' => $this->pdfBytes(),
            default => throw new \LogicException($kind),
        };
    }

    private function assertNothingStored(int $id): void
    {
        $expense = $this->findExpense($id);
        self::assertInstanceOf(Expense::class, $expense);
        self::assertNull($expense->getReceipt());
        self::assertSame([], $this->receiptFiles());
    }

    /**
     * Le client de test transforme un fichier plus gros que `upload_max_filesize` en erreur d'upload
     * (UPLOAD_ERR_INI_SIZE) : sous 5 Mo, les tests de taille échoueraient (ou passeraient) pour une autre raison.
     */
    private function assertUploadLimitAllowsTheTest(): void
    {
        self::assertGreaterThan(
            self::MAX_BYTES + 1,
            UploadedFile::getMaxFilesize(),
            'upload_max_filesize ('.\ini_get('upload_max_filesize').') doit dépasser 5 Mo pour tester la limite de C3 (et pour déposer un justificatif de 5 Mo en vrai).',
        );
    }
}
