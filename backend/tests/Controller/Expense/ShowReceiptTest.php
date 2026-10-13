<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * GET /api/expenses/{id}/receipt — spec 005 (C5) : le fichier lui-même, affiché par le navigateur.
 * Lecteurs : le propriétaire, ou son responsable si la note n'est pas un brouillon ; 404 sinon ; 401 sans session.
 */
final class ShowReceiptTest extends ApiTestCase
{
    // --- C5 : ouverture par le propriétaire et par le responsable --------------------------

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function storedFiles(): iterable
    {
        yield 'PNG' => ['png', 'recu-hotel.png', 'image/png'];
        yield 'JPEG' => ['jpeg', 'ticket-taxi.jpg', 'image/jpeg'];
        yield 'PDF' => ['pdf', 'facture.pdf', 'application/pdf'];
    }

    #[DataProvider('storedFiles')]
    public function testC5LeProprietaireOuvreSonJustificatifAfficheParLeNavigateur(string $kind, string $name, string $mimeType): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $content = match ($kind) {
            'png' => $this->pngBytes(),
            'jpeg' => $this->jpegBytes(),
            default => $this->pdfBytes(),
        };
        $this->login($team['sophie']);
        $this->uploadReceipt($id, $this->uploadedFile($content, $name, $mimeType));
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        self::assertResponseStatusCodeSame(200);
        $this->assertServedFile($content, $name, $mimeType);
    }

    public function testC5LeTypeServiEstLeTypeDetecteEtNonLeTypeAnnonce(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $content = $this->pngBytes();
        $this->login($team['sophie']);
        $this->uploadReceipt($id, $this->uploadedFile($content, 'facture.pdf', 'application/pdf'));
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        self::assertResponseStatusCodeSame(200);
        $this->assertServedFile($content, 'facture.pdf', 'image/png');
    }

    /**
     * @return iterable<string, array{ExpenseStatus}>
     */
    public static function visibleToManagerStatuses(): iterable
    {
        yield 'soumise' => [ExpenseStatus::Submitted];
        yield 'validée' => [ExpenseStatus::Approved];
        yield 'rejetée' => [ExpenseStatus::Rejected];
    }

    #[DataProvider('visibleToManagerStatuses')]
    public function testC5LeResponsableOuvreLeJustificatifDUneNoteHorsBrouillon(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $content = $this->attachPng($id, 'recu-hotel.png');
        $this->forceStatus($id, $status);
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        self::assertResponseStatusCodeSame(200);
        $this->assertServedFile($content, 'recu-hotel.png', 'image/png');
    }

    public function testC5LeJustificatifApparaitDansLaNoteVueParLeResponsable(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $content = $this->attachPng($id, 'recu-hotel.png');
        $this->forceStatus($id, ExpenseStatus::Submitted);
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame(['name' => 'recu-hotel.png', 'mimeType' => 'image/png', 'sizeBytes' => \strlen($content)], $view['receipt']);

        $this->client->request('GET', '/api/team/expenses');
        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertSame([$id], array_column($list, 'id'));
        $this->assertExpenseView($list[0]);
        self::assertSame($view['receipt'], $list[0]['receipt']);
    }

    // --- Cas limite : note sans justificatif ------------------------------------------------

    public function testCasLimiteOuvrirLeJustificatifDUneNoteQuiNEnAPasRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
    }

    public function testCasLimiteApresRetraitLOuvertureRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        $this->assertErrorResponse(404);
    }

    /** Relecture F7 : un nom non ASCII (accents, guillemets) s'ouvre, avec un repli ASCII dans l'en-tête. */
    public function testRelectureUnNomNonAsciiSOuvreAvecSonNomEncode(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $content = $this->pngBytes();
        $this->uploadReceipt($id, $this->uploadedFile($content, 'Reçu « hôtel » "n°1".png', 'image/png'));
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        self::assertResponseStatusCodeSame(200);
        $disposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');
        self::assertStringStartsWith('inline', $disposition);
        self::assertStringContainsString("filename*=utf-8''".rawurlencode('Reçu « hôtel » "n°1".png'), $disposition);
        self::assertSame($content, $this->client->getInternalResponse()->getContent());
    }

    /** Revue F6 : un fichier absent du disque (données rechargées à la main) répond 404, pas 500. */
    public function testRevueUnFichierAbsentDuStockageRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        foreach ($this->receiptFiles() as $file) {
            unlink($file);
        }

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        $error = $this->assertErrorResponse(404);
        self::assertStringNotContainsString('receipts', $error['message']);
    }

    // --- Autorisations ---------------------------------------------------------------------

    public function testAutorisationOuvertureSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        $this->client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        $this->assertErrorResponse(401);
        self::assertStringNotContainsString("\x89PNG", (string) $this->client->getInternalResponse()->getContent());
    }

    public function testAutorisationLeResponsableNOuvrePasLeJustificatifDUnBrouillon404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        $this->assertErrorResponse(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function strangers(): iterable
    {
        yield 'collègue' => ['tom'];
        yield 'manager d\'une autre équipe' => ['julie'];
        yield 'admin (responsable du responsable)' => ['admin'];
    }

    #[DataProvider('strangers')]
    public function testAutorisationUnAutreLecteurRecoit404(string $reader): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        $this->forceStatus($id, ExpenseStatus::Submitted);
        $this->login($team[$reader]);

        $this->client->request('GET', '/api/expenses/'.$id.'/receipt');

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
    }

    public function testAutorisationOuvertureDUneNoteInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);

        $this->client->request('GET', '/api/expenses/'.($id + 1000).'/receipt');

        $this->assertErrorResponse(404);
    }

    private function assertServedFile(string $content, string $originalName, string $mimeType): void
    {
        $response = $this->client->getResponse();
        self::assertStringStartsWith($mimeType, (string) $response->headers->get('Content-Type'));
        $disposition = (string) $response->headers->get('Content-Disposition');
        self::assertStringStartsWith('inline', $disposition);
        self::assertStringContainsString($originalName, $disposition);
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        // Contenu d'une réponse fichier (BinaryFileResponse) : capturé par le client de test.
        self::assertSame($content, $this->client->getInternalResponse()->getContent());
    }
}
