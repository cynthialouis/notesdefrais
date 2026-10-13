<?php

namespace App\Tests\Service;

use App\Service\PolicyChecker;
use App\Tests\Support\ApiTestCase;

/**
 * App\Service\PolicyChecker::check(Expense)->receiptMissing — spec 005 (C9) :
 * vrai si la catégorie exige un justificatif et que la note n'en a pas.
 * Service appelé directement ; le justificatif est déposé par la route (seul chemin d'écriture).
 */
final class PolicyCheckerReceiptTest extends ApiTestCase
{
    private function checker(): PolicyChecker
    {
        $checker = static::getContainer()->get(PolicyChecker::class);
        self::assertInstanceOf(PolicyChecker::class, $checker);

        return $checker;
    }

    public function testC9JustificatifObligatoireEtAbsentLeSignaleManquant(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $expense = $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement');

        $check = $this->checker()->check($expense);

        self::assertTrue($check->receiptRequired);
        self::assertTrue($check->receiptMissing);
    }

    public function testC9JustificatifObligatoireEtPresentNeLeSignalePas(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $id = (int) $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement')->getId();
        $this->login($team['sophie']);
        $this->attachPng($id);

        $expense = $this->findExpense($id);
        self::assertNotNull($expense);
        $check = $this->checker()->check($expense);

        self::assertTrue($check->receiptRequired);
        self::assertFalse($check->receiptMissing);
    }

    public function testC9JustificatifFacultatifNeManqueJamais(): void
    {
        $team = $this->createTeam();
        $this->setPolicy('repas', 5000, false);
        $without = $this->createExpense($team['sophie'], 'Déjeuner', 1200, '2026-10-01', 'repas');
        $withId = (int) $this->createExpense($team['sophie'], 'Dîner', 2400, '2026-10-02', 'repas')->getId();
        $this->login($team['sophie']);
        $this->attachPng($withId);

        self::assertFalse($this->checker()->check($without)->receiptMissing);
        $with = $this->findExpense($withId);
        self::assertNotNull($with);
        self::assertFalse($this->checker()->check($with)->receiptMissing);
    }
}
