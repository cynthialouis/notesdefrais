<?php

namespace App\Tests\Service;

use App\Enum\ExpenseStatus;
use App\Service\PolicyCheck;
use App\Service\PolicyChecker;
use App\Tests\Support\ApiTestCase;

/**
 * App\Service\PolicyChecker::check(Expense): PolicyCheck — spec 004 (C5, C7, C9).
 * Service testé directement (KernelTestCase via ApiTestCase pour les helpers de données) ;
 * la validation passe par la route pour que le Workflow fige le plafond.
 */
final class PolicyCheckerTest extends ApiTestCase
{
    private function checker(): PolicyChecker
    {
        $checker = static::getContainer()->get(PolicyChecker::class);
        self::assertInstanceOf(PolicyChecker::class, $checker);

        return $checker;
    }

    public function testC7SansPlafondNiJustificatif(): void
    {
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], 'Divers', 500000, '2026-10-01', 'autre');

        $check = $this->checker()->check($expense);

        self::assertInstanceOf(PolicyCheck::class, $check);
        self::assertNull($check->limitCents);
        self::assertNull($check->overLimitByCents);
        self::assertFalse($check->receiptRequired);
    }

    public function testC5MontantEgalAuPlafondConforme(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $expense = $this->createExpense($team['sophie'], 'Déjeuner', 5000, '2026-10-01', 'repas');

        $check = $this->checker()->check($expense);

        self::assertSame(5000, $check->limitCents);
        self::assertNull($check->overLimitByCents);
    }

    public function testC5MontantInferieurAuPlafondConforme(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $expense = $this->createExpense($team['sophie'], 'Déjeuner', 1, '2026-10-01', 'repas');

        $check = $this->checker()->check($expense);

        self::assertSame(5000, $check->limitCents);
        self::assertNull($check->overLimitByCents);
    }

    public function testC7DepassementCalculeEnCentimes(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('transport', 30000);
        $expense = $this->createExpense($team['sophie'], 'Train', 30001, '2026-10-01', 'transport');

        $check = $this->checker()->check($expense);

        self::assertSame(30000, $check->limitCents);
        self::assertSame(1, $check->overLimitByCents);
        self::assertFalse($check->receiptRequired);
    }

    public function testC7JustificatifObligatoireSelonLaCategorie(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('materiel', 50000, true);
        $expense = $this->createExpense($team['sophie'], 'Écran', 20000, '2026-10-01', 'materiel');

        $check = $this->checker()->check($expense);

        self::assertSame(50000, $check->limitCents);
        self::assertNull($check->overLimitByCents);
        self::assertTrue($check->receiptRequired);
    }

    public function testC9UneNoteNonValideeSuitLePlafondActuel(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000);
        $expense = $this->createExpense($team['sophie'], 'Déjeuner', 4000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        self::assertNull($this->checker()->check($expense)->overLimitByCents);

        $this->setPolicy('repas', 3500);

        $check = $this->checker()->check($expense);
        self::assertSame(3500, $check->limitCents);
        self::assertSame(500, $check->overLimitByCents);
    }

    public function testC9UneNoteValideeUtiliseLePlafondFigeEtLeJustificatifActuel(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 14000, '2026-10-01', 'hebergement', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00')->getId();
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);

        $this->setPolicy('hebergement', 10000, true);
        $expense = $this->findExpense($id);
        self::assertNotNull($expense);
        self::assertSame(15000, $expense->getLimitAtApprovalCents());

        $check = $this->checker()->check($expense);
        self::assertSame(15000, $check->limitCents);
        self::assertNull($check->overLimitByCents);
        // Le justificatif obligatoire reste la valeur actuelle de la catégorie.
        self::assertTrue($check->receiptRequired);
    }

    public function testC9UneNoteValideeSansPlafondFigeNEstPasSignalee(): void
    {
        // Note validée sans plafond figé (catégorie sans plafond au moment de la validation).
        $team = $this->createTeam();
        $expense = $this->createExpense($team['sophie'], 'Dîner', 9000, '2026-10-01', 'repas', ExpenseStatus::Approved, '2026-10-02T09:00:00+02:00');
        $this->setPolicy('repas', 5000);

        $check = $this->checker()->check($expense);

        self::assertNull($check->limitCents);
        self::assertNull($check->overLimitByCents);
    }
}
