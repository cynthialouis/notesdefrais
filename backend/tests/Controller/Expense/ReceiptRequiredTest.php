<?php

namespace App\Tests\Controller\Expense;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Justificatif obligatoire — spec 005 (C8, C9) :
 * - `policy.receiptMissing` : vrai si la catégorie exige un justificatif et que la note n'en a pas ;
 * - POST /api/expenses/{id}/submit : 409 si receiptMissing, après les refus d'état puis d'absence de responsable.
 */
final class ReceiptRequiredTest extends ApiTestCase
{
    // --- C9 : chaque note indique si un justificatif obligatoire lui manque -----------------

    public function testC9UneNoteDUneCategorieAJustificatifObligatoireSansJustificatifLeSignale(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertNull($view['receipt']);
        self::assertSame(['limitCents' => 15000, 'overLimitByCents' => null, 'receiptRequired' => true, 'receiptMissing' => true], $view['policy']);
    }

    public function testC9LeDepotLeveLeSignalementEtLeRetraitLeRetablit(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);

        $this->attachPng($id);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertTrue($view['policy']['receiptRequired']);
        self::assertFalse($view['policy']['receiptMissing']);

        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');
        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        self::assertTrue($view['policy']['receiptMissing']);
    }

    public function testC9UneCategorieSansJustificatifObligatoireNeSignaleRien(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000, false);
        $id = (int) $this->createExpense($team['sophie'], 'Déjeuner', 1200, '2026-10-01', 'repas')->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['limitCents' => 5000, 'overLimitByCents' => null, 'receiptRequired' => false, 'receiptMissing' => false], $this->responseJson()['policy'] ?? null);
    }

    public function testC9LeSignalementFigureDansLaListeDuProprietaireEtDansLaListeATraiter(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $missing = (int) $this->createExpense($team['sophie'], 'Hôtel sans reçu', 12000, '2026-10-02', 'hebergement')->getId();
        $complete = (int) $this->createExpense($team['sophie'], 'Hôtel avec reçu', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $this->attachPng($complete);

        $this->client->request('GET', '/api/expenses');
        self::assertResponseStatusCodeSame(200);
        $byId = array_column($this->responseJson(), null, 'id');
        $this->assertExpenseView($byId[$missing]);
        self::assertTrue($byId[$missing]['policy']['receiptMissing']);
        self::assertFalse($byId[$complete]['policy']['receiptMissing']);

        $this->client->request('POST', '/api/expenses/'.$complete.'/submit');
        self::assertResponseStatusCodeSame(200);
        $this->login($team['marc']);
        $this->client->request('GET', '/api/team/expenses');
        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertSame([$complete], array_column($list, 'id'));
        $this->assertExpenseView($list[0]);
        self::assertFalse($list[0]['policy']['receiptMissing']);
    }

    public function testC9LeSignalementSuitLaPolitiqueActuelleDeLaCategorie(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Clavier', 3999, '2026-10-01', 'materiel')->getId();
        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertFalse($this->responseJson()['policy']['receiptMissing']);

        $this->login($team['admin']);
        $this->jsonRequest('PUT', '/api/policies/materiel', ['maxAmountCents' => 50000, 'receiptRequired' => true]);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->responseJson()['policy']['receiptMissing']);
    }

    // --- C8 : soumission refusée sans justificatif obligatoire ------------------------------

    public function testC8SoumettreSansJustificatifObligatoireRenvoie409EtLaisseLeBrouillon(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringContainsString('justificatif', $error['message']);
        $expense = $this->findExpense($id);
        self::assertSame(ExpenseStatus::Draft, $expense?->getStatus());
        self::assertNull($expense?->getSubmittedAt());

        $this->client->request('GET', '/api/expenses/'.$id.'/events');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['created'], array_column($this->responseJson(), 'type'));
    }

    public function testC8AvecSonJustificatifLaNoteSeSoumet(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id, 'recu-hotel.png');

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame('submitted', $view['status']);
        self::assertSame('recu-hotel.png', $view['receipt']['name'] ?? null);
        self::assertSame(['limitCents' => 15000, 'overLimitByCents' => null, 'receiptRequired' => true, 'receiptMissing' => false], $view['policy']);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }

    public function testC8UneCategorieSansJustificatifObligatoireSeSoumetSansJustificatif(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000, false);
        $id = (int) $this->createExpense($team['sophie'], 'Déjeuner', 1200, '2026-10-01', 'repas')->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        self::assertResponseStatusCodeSame(200);
        self::assertNull($this->responseJson()['receipt'] ?? 'absent');
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }

    public function testC8UnJustificatifRetireAvantLaSoumissionBloqueLaSoumission(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('materiel', 50000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Écran', 20000, '2026-10-01', 'materiel')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);
        $this->client->request('DELETE', '/api/expenses/'.$id.'/receipt');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertStringContainsString('justificatif', $error['message']);
        self::assertSame(ExpenseStatus::Draft, $this->findExpense($id)?->getStatus());
    }

    // --- C8 : ordre des refus : état, puis responsable, puis justificatif ------------------

    /**
     * @return iterable<string, array{ExpenseStatus}>
     */
    public static function lockedStatuses(): iterable
    {
        yield 'soumise' => [ExpenseStatus::Submitted];
        yield 'validée' => [ExpenseStatus::Approved];
    }

    #[DataProvider('lockedStatuses')]
    public function testC8LeRefusDEtatPasseAvantLeJustificatifManquant(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement', $status, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertSame('Seule une note en brouillon ou rejetée peut être soumise.', $error['message']);
        self::assertSame($status, $this->findExpense($id)?->getStatus());
    }

    public function testC8LAbsenceDeResponsablePasseAvantLeJustificatifManquant(): void
    {
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($admin, 'Hôtel', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($admin);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertStringContainsString('responsable', $error['message']);
        self::assertStringNotContainsString('justificatif', $error['message']);
        self::assertSame(ExpenseStatus::Draft, $this->findExpense($id)?->getStatus());
    }

    // --- Cas limite : note rejetée corrigée puis resoumise ---------------------------------

    public function testCasLimiteUneNoteRejeteeSansJustificatifObligatoireNeSeResoumetPas(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement', ExpenseStatus::Rejected, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        $error = $this->assertErrorResponse(409);
        self::assertStringContainsString('justificatif', $error['message']);
        self::assertSame(ExpenseStatus::Rejected, $this->findExpense($id)?->getStatus());
    }

    public function testCasLimiteLeJustificatifDUneNoteRejeteeSeRemplaceAvantLaResoumission(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id, 'illisible.png');
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Reçu illisible, merci de le remplacer.']);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->uploadReceipt($id, $this->uploadedFile($this->pdfBytes(), 'recu-lisible.pdf', 'application/pdf'));
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        self::assertSame('submitted', $view['status']);
        self::assertSame('recu-lisible.pdf', $view['receipt']['name'] ?? null);
        self::assertSame('recu-lisible.pdf', $this->findExpense($id)?->getReceipt()?->getOriginalName());
        self::assertCount(1, $this->receiptFiles());
    }

    // --- Cas limite : la politique change après la soumission -------------------------------

    public function testCasLimiteUneNoteDejaSoumiseNEstPasAffecteeParUnePolitiquePlusStricte(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Clavier', 3999, '2026-10-01', 'materiel')->getId();
        $this->login($team['sophie']);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);

        $this->login($team['admin']);
        $this->jsonRequest('PUT', '/api/policies/materiel', ['maxAmountCents' => 50000, 'receiptRequired' => true]);
        self::assertResponseStatusCodeSame(200);

        // La note reste soumise et peut toujours être décidée par le responsable.
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
        $this->login($team['marc']);
        $this->client->request('GET', '/api/team/expenses');
        self::assertSame([$id], array_column($this->responseJson(), 'id'));
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(ExpenseStatus::Approved, $this->findExpense($id)?->getStatus());
    }

    public function testCasLimiteLaNouvellePolitiqueSAppliqueALaSoumissionSuivante(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Clavier', 3999, '2026-10-01', 'materiel')->getId();
        $this->login($team['sophie']);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);

        $this->login($team['admin']);
        $this->jsonRequest('PUT', '/api/policies/materiel', ['maxAmountCents' => 50000, 'receiptRequired' => true]);
        self::assertResponseStatusCodeSame(200);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Le justificatif est désormais obligatoire.']);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        $error = $this->assertErrorResponse(409);
        self::assertStringContainsString('justificatif', $error['message']);
        self::assertSame(ExpenseStatus::Rejected, $this->findExpense($id)?->getStatus());

        $this->attachPng($id);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }
}
