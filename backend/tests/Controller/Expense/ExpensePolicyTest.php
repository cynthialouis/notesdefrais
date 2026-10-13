<?php

namespace App\Tests\Controller\Expense;

use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Signalement « hors politique » d'une note (ExpenseView.policy) — spec 004 (C5, C6, C7, C9).
 * policy = { limitCents, overLimitByCents, receiptRequired }, calculé par le serveur ; la spec 005 (C9) y ajoute
 * receiptMissing (justificatif obligatoire manquant).
 */
final class ExpensePolicyTest extends ApiTestCase
{
    private const OVER = ['limitCents' => 5000, 'overLimitByCents' => 1000, 'receiptRequired' => false, 'receiptMissing' => false];

    // --- C5 : égal = conforme ; sans plafond : aucune alerte -------------------------------

    /**
     * @return iterable<string, array{int, int|null}>
     */
    public static function amountsAroundLimit(): iterable
    {
        yield 'en dessous' => [4999, null];
        yield 'égal au plafond' => [5000, null];
        yield 'un centime au-dessus' => [5001, 1];
        yield 'nettement au-dessus' => [12000, 7000];
    }

    #[DataProvider('amountsAroundLimit')]
    public function testC5MontantEgalAuPlafondConformeEtDepassementCalcule(int $amountCents, ?int $expectedOver): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Déjeuner', $amountCents, '2026-10-01', 'repas')->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame(['limitCents' => 5000, 'overLimitByCents' => $expectedOver, 'receiptRequired' => false, 'receiptMissing' => false], $view['policy']);
    }

    public function testC5CategorieSansPlafondAucuneAlerte(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Formation', 999999, '2026-10-01', 'autre')->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['limitCents' => null, 'overLimitByCents' => null, 'receiptRequired' => false, 'receiptMissing' => false], $this->responseJson()['policy'] ?? null);
    }

    // --- C6 : signalement visible du propriétaire et du responsable, listes et détail -------

    public function testC6LeProprietaireVoitLeSignalementDansLaListeEtLeDetail(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $over = $this->createExpense($team['sophie'], 'Dîner client', 6000, '2026-10-02', 'repas');
        $ok = $this->createExpense($team['sophie'], 'Sandwich', 900, '2026-10-01', 'repas');
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses');

        self::assertResponseStatusCodeSame(200);
        $byId = array_column($this->responseJson(), null, 'id');
        $this->assertExpenseView($byId[$over->getId()]);
        self::assertSame(self::OVER, $byId[$over->getId()]['policy']);
        self::assertSame(['limitCents' => 5000, 'overLimitByCents' => null, 'receiptRequired' => false, 'receiptMissing' => false], $byId[$ok->getId()]['policy'] ?? null);

        $this->client->request('GET', '/api/expenses/'.$over->getId());
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::OVER, $this->responseJson()['policy'] ?? null);
    }

    public function testC6LeResponsableVoitLeMemeSignalementDansLaListeATraiterEtLeDetail(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Dîner client', 6000, '2026-10-02', 'repas', ExpenseStatus::Submitted, '2026-10-03T09:00:00+02:00')->getId();

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);
        $ownerPolicy = $this->responseJson()['policy'] ?? null;

        $this->login($team['marc']);
        $this->client->request('GET', '/api/team/expenses');
        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertSame([$id], array_column($list, 'id'));
        $this->assertExpenseView($list[0]);
        self::assertSame(self::OVER, $list[0]['policy']);

        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);
        $managerPolicy = $this->responseJson()['policy'] ?? null;

        self::assertSame(self::OVER, $ownerPolicy);
        self::assertSame($ownerPolicy, $managerPolicy);
    }

    public function testC6UnManagerHorsEquipeNeVoitPasLaNoteNiSonSignalement(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Dîner client', 6000, '2026-10-02', 'repas', ExpenseStatus::Submitted, '2026-10-03T09:00:00+02:00')->getId();
        $this->login($team['julie']);

        $this->client->request('GET', '/api/expenses/'.$id);

        $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('policy', $this->responseJson());
    }

    public function testC6LeSignalementPorteLeJustificatifObligatoire(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 10000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/'.$id);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['limitCents' => 15000, 'overLimitByCents' => null, 'receiptRequired' => true, 'receiptMissing' => true], $this->responseJson()['policy'] ?? null);
    }

    // --- C7 : calcul côté serveur, dans toutes les réponses qui renvoient une note ---------

    public function testC7LaCreationRenvoieLeSignalementCalculeParLeServeur(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('transport', 30000);
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['amountCents' => 32500, 'categorySlug' => 'transport']));

        self::assertResponseStatusCodeSame(201);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame(['limitCents' => 30000, 'overLimitByCents' => 2500, 'receiptRequired' => false, 'receiptMissing' => false], $view['policy']);
        // La création n'est pas bloquée par le dépassement.
        self::assertSame(32500, $this->findExpense($view['id'])?->getAmountCents());
    }

    public function testC7LaModificationRecalculeLeSignalementSelonLaNouvelleCategorie(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $this->setPolicy('materiel', 50000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Achat', 6000, '2026-10-01', 'repas')->getId();
        $this->login($team['sophie']);

        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['label' => 'Souris', 'amountCents' => 6000, 'categorySlug' => 'materiel']));

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame(['limitCents' => 50000, 'overLimitByCents' => null, 'receiptRequired' => true, 'receiptMissing' => true], $view['policy']);
        self::assertSame('materiel', $this->findExpense($id)?->getCategory()->getSlug());
    }

    public function testC7UnSignalementEnvoyeParLeClientEstIgnore(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $this->login($team['sophie']);

        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload([
            'amountCents' => 8000,
            'categorySlug' => 'repas',
            'policy' => ['limitCents' => null, 'overLimitByCents' => null, 'receiptRequired' => false],
        ]));

        // Champ inconnu ignoré par la désérialisation (comportement actuel de POST /api/expenses) : jamais pris en compte.
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['limitCents' => 5000, 'overLimitByCents' => 3000, 'receiptRequired' => false, 'receiptMissing' => false], $this->responseJson()['policy'] ?? null);
    }

    public function testC7LaSoumissionAuDessusDuPlafondEstAccepteeEtSignalee(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Dîner client', 9000, '2026-10-01', 'repas')->getId();
        $this->login($team['sophie']);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        self::assertResponseStatusCodeSame(200);
        $view = $this->responseJson();
        $this->assertExpenseView($view);
        self::assertSame('submitted', $view['status']);
        self::assertSame(['limitCents' => 5000, 'overLimitByCents' => 4000, 'receiptRequired' => false, 'receiptMissing' => false], $view['policy']);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }

    public function testC7UneNoteDUneCategorieAJustificatifObligatoireSeSoumetAvecSonJustificatif(): void
    {
        // Spec 005 (C8) : sans justificatif, la soumission serait refusée ; le signalement reste renvoyé à la soumission.
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);

        $this->client->request('POST', '/api/expenses/'.$id.'/submit');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['limitCents' => 15000, 'overLimitByCents' => null, 'receiptRequired' => true, 'receiptMissing' => false], $this->responseJson()['policy'] ?? null);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());
    }

    public function testC7LesDecisionsRenvoientLeSignalement(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $toApprove = (int) $this->createExpense($team['sophie'], 'Dîner A', 6000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $toReject = (int) $this->createExpense($team['tom'], 'Dîner B', 6000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.$toApprove.'/approve');
        self::assertResponseStatusCodeSame(200);
        $approved = $this->responseJson();
        $this->assertExpenseView($approved);
        self::assertSame(self::OVER, $approved['policy']);

        $this->jsonRequest('POST', '/api/expenses/'.$toReject.'/reject', ['reason' => 'Dépassement du plafond repas.']);
        self::assertResponseStatusCodeSame(200);
        $rejected = $this->responseJson();
        $this->assertExpenseView($rejected);
        self::assertSame(self::OVER, $rejected['policy']);
    }

    // --- C9 : recalcul tant que la note n'est pas validée, figé à la validation ------------

    /**
     * @return iterable<string, array{ExpenseStatus}>
     */
    public static function notApprovedStatuses(): iterable
    {
        yield 'brouillon' => [ExpenseStatus::Draft];
        yield 'soumise' => [ExpenseStatus::Submitted];
        yield 'rejetée' => [ExpenseStatus::Rejected];
    }

    #[DataProvider('notApprovedStatuses')]
    public function testC9UneNoteNonValideeEstSignaleeSelonLePlafondAbaisse(ExpenseStatus $status): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $submittedAt = ExpenseStatus::Draft === $status ? null : '2026-10-02T09:00:00+02:00';
        $id = (int) $this->createExpense($team['sophie'], 'Déjeuner', 4000, '2026-10-01', 'repas', $status, $submittedAt)->getId();

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertSame(['limitCents' => 5000, 'overLimitByCents' => null, 'receiptRequired' => false, 'receiptMissing' => false], $this->responseJson()['policy'] ?? null);

        $this->login($team['admin']);
        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => 3000, 'receiptRequired' => false]);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['limitCents' => 3000, 'overLimitByCents' => 1000, 'receiptRequired' => false, 'receiptMissing' => false], $this->responseJson()['policy'] ?? null);
        self::assertNull($this->findExpense($id)?->getLimitAtApprovalCents());
    }

    public function testC9LaValidationFigeLePlafondEnVigueur(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Déjeuner', 4000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        self::assertNull($this->findExpense($id)?->getLimitAtApprovalCents());
        $this->login($team['marc']);

        $this->client->request('POST', '/api/expenses/'.$id.'/approve');

        self::assertResponseStatusCodeSame(200);
        $stored = $this->findExpense($id);
        self::assertInstanceOf(Expense::class, $stored);
        self::assertSame(ExpenseStatus::Approved, $stored->getStatus());
        self::assertSame(5000, $stored->getLimitAtApprovalCents());
    }

    public function testC9LeRejetNeFigePasLePlafond(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Déjeuner', 6000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);

        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Plafond dépassé.']);

        self::assertResponseStatusCodeSame(200);
        self::assertNull($this->findExpense($id)?->getLimitAtApprovalCents());
    }

    public function testC9UneNoteValideeGardeSonSignalementApresUneBaisseDuPlafond(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Déjeuner', 4000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);

        $this->login($team['admin']);
        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => 3000, 'receiptRequired' => false]);
        self::assertResponseStatusCodeSame(200);

        foreach (['sophie', 'marc'] as $reader) {
            $this->login($team[$reader]);
            $this->client->request('GET', '/api/expenses/'.$id);
            self::assertResponseStatusCodeSame(200);
            self::assertSame(['limitCents' => 5000, 'overLimitByCents' => null, 'receiptRequired' => false, 'receiptMissing' => false], $this->responseJson()['policy'] ?? null, "Lecture par $reader");
        }
        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses');
        $byId = array_column($this->responseJson(), null, 'id');
        self::assertSame(['limitCents' => 5000, 'overLimitByCents' => null, 'receiptRequired' => false, 'receiptMissing' => false], $byId[$id]['policy'] ?? null);
        self::assertSame(5000, $this->findExpense($id)?->getLimitAtApprovalCents());
    }

    public function testC9UneNoteValideeHorsPlafondResteSignaleeApresUneHausseDuPlafond(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $id = (int) $this->createExpense($team['sophie'], 'Dîner', 6000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);

        $this->login($team['admin']);
        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => 10000, 'receiptRequired' => false]);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::OVER, $this->responseJson()['policy'] ?? null);
    }

    public function testC9UneNoteValideeSansPlafondResteConformeSiUnPlafondEstAjoute(): void
    {
        $team = $this->createTeam();
        $id = (int) $this->createExpense($team['sophie'], 'Dîner', 9000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);
        self::assertNull($this->findExpense($id)?->getLimitAtApprovalCents());

        $this->login($team['admin']);
        $this->jsonRequest('PUT', '/api/policies/repas', ['maxAmountCents' => 1000, 'receiptRequired' => false]);
        self::assertResponseStatusCodeSame(200);

        $this->login($team['sophie']);
        $this->client->request('GET', '/api/expenses/'.$id);
        self::assertResponseStatusCodeSame(200);
        $policy = $this->responseJson()['policy'] ?? null;
        self::assertIsArray($policy);
        self::assertNull($policy['limitCents']);
        self::assertNull($policy['overLimitByCents']);
    }
}
