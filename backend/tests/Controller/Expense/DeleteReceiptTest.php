<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * DELETE /api/expenses/{id}/receipt (propriétaire) — spec 005 (C6, C7), et effacement du fichier
 * à la suppression de la note, DELETE /api/expenses/{id} (C11).
 * Réponse du retrait : 200 avec l'ExpenseView mise à jour (`receipt` null).
 */
final class DeleteReceiptTest extends ApiTestCase
{
    // --- C6 : retrait tant que la note est modifiable ---------------------------------------

    /**
     * @return iterable<string, array{ExpenseStatus}>
     */
    public static function editableStatuses(): iterable
    {
        yield 'brouillon' => [ExpenseStatus::Draft];
        yield 'rejetée' => [ExpenseStatus::Rejected];
    }

    #[DataProvider('editableStatuses')]
    public function testC6LeProprietaireRetireSonJustificatifEtLeFichierEstEfface(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id, 'recu.png');
        $files = $this->receiptFiles();
        self::assertCount(1, $files);
        $this->forceStatus($id, $status);

        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame($id, $view['id']);
        self::assertSame($status->value, $view['status']);
        self::assertNull($view['receipt']);

        $expense = $this->findExpense($id);
        self::assertNotNull($expense);
        self::assertNull($expense->getReceipt());
        self::assertSame($status, $expense->getStatus());
        self::assertFileDoesNotExist($files[0]);
        self::assertSame([], $this->receiptFiles());
    }

    public function testC6LeRetraitNeTouchePasAuJustificatifDesAutresNotes(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $other = (int) $this->createExpense($team['sophie'], 'Autre')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id, 'a.png');
        $this->attachPng($other, 'b.png');
        self::assertCount(2, $this->receiptFiles());

        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('b.png', $this->findExpense($other)?->getReceipt()?->getOriginalName());
        self::assertCount(1, $this->receiptFiles());
    }

    public function testC6ApresRetraitUnNouveauDepotEstPossible(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id, 'premier.png');
        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');
        self::assertResponseStatusCodeSame(200);

        $this->attachPng($id, 'second.png');

        self::assertSame('second.png', $this->responseJson()['receipt']['name'] ?? null);
        self::assertSame('second.png', $this->findExpense($id)?->getReceipt()?->getOriginalName());
        self::assertCount(1, $this->receiptFiles());
    }

    // --- C7 : note non modifiable → 409, ni la base ni le disque ne changent ---------------

    /**
     * @return iterable<string, array{ExpenseStatus}>
     */
    public static function lockedStatuses(): iterable
    {
        yield 'soumise' => [ExpenseStatus::Submitted];
        yield 'validée' => [ExpenseStatus::Approved];
    }

    #[DataProvider('lockedStatuses')]
    public function testC7RetirerLeJustificatifDUneNoteNonModifiableRenvoie409(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $content = $this->attachPng($id, 'recu.png');
        $before = $this->receiptFiles();
        $this->forceStatus($id, $status);

        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        $expense = $this->findExpense($id);
        self::assertSame('recu.png', $expense?->getReceipt()?->getOriginalName());
        self::assertSame($status, $expense?->getStatus());
        self::assertSame($before, $this->receiptFiles());
        self::assertSame($content, file_get_contents($before[0]));
    }

    // --- Autorisations ----------------------------------------------------------------------

    public function testAutorisationRetraitSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        $before = $this->receiptFiles();
        $this->client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');

        $this->assertErrorResponse(401);
        self::assertNotNull($this->findExpense($id)?->getReceipt());
        self::assertSame($before, $this->receiptFiles());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonOwners(): iterable
    {
        yield 'responsable' => ['marc'];
        yield 'collègue' => ['tom'];
        yield 'admin' => ['admin'];
    }

    #[DataProvider('nonOwners')]
    public function testAutorisationSeulLeProprietaireRetireLeJustificatif404(string $reader): void
    {
        $team = $this->createTeam();
        // Note rejetée : modifiable par le propriétaire et lisible par le responsable.
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        $before = $this->receiptFiles();
        $this->forceStatus($id, ExpenseStatus::Rejected);
        $this->login($team[$reader]);

        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertNotNull($this->findExpense($id)?->getReceipt());
        self::assertSame($before, $this->receiptFiles());
    }

    public function testAutorisationRetraitSurUneNoteInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);

        $this->client->request('DELETE', '/api/expenses/'.($id + 1000).'/receipt');

        $this->assertErrorResponse(404);
        self::assertCount(1, $this->receiptFiles());
    }

    // --- C11 : la suppression de la note efface son justificatif ---------------------------

    #[DataProvider('editableStatuses')]
    public function testC11SupprimerUneNoteEffaceSonJustificatif(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $kept = (int) $this->createExpense($team['sophie'], 'Gardée')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id, 'supprime.png');
        $this->attachPng($kept, 'garde.png');
        $keptStorageName = (string) $this->findExpense($kept)?->getReceipt()?->getStorageName();
        self::assertCount(2, $this->receiptFiles());
        $this->forceStatus($id, $status);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->findExpense($id));
        $files = $this->receiptFiles();
        self::assertCount(1, $files);
        self::assertStringEndsWith($keptStorageName, $files[0]);
        self::assertSame('garde.png', $this->findExpense($kept)?->getReceipt()?->getOriginalName());
    }

    public function testC11UneSuppressionRefuseeConserveLeJustificatif(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12900, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        $before = $this->receiptFiles();
        $this->forceStatus($id, ExpenseStatus::Submitted);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        $this->assertErrorResponse(409);
        self::assertNotNull($this->findExpense($id)?->getReceipt());
        self::assertSame($before, $this->receiptFiles());
    }

    public function testC11SupprimerUneNoteSansJustificatifResteNominal(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'])->getId();
        $this->login($team['sophie']);

        $this->client->request('DELETE', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->findExpense($id));
        self::assertSame([], $this->receiptFiles());
    }
}
