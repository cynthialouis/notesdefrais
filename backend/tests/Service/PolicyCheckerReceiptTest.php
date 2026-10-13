<?php

namespace App\Tests\Service;

use App\Enum\ExpenseStatus;
use App\Service\PolicyChecker;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    /**
     * @return iterable<string, array{ExpenseStatus, bool}>
     */
    public static function statuses(): iterable
    {
        yield 'brouillon' => [ExpenseStatus::Draft, true];
        yield 'rejetée' => [ExpenseStatus::Rejected, true];
        yield 'soumise' => [ExpenseStatus::Submitted, false];
        yield 'validée' => [ExpenseStatus::Approved, false];
    }

    /** C9 précisé le 13/10 : le manque ne vaut que pour une note encore modifiable (donc à soumettre). */
    #[DataProvider('statuses')]
    public function testC9LeManqueNeVautQuePourUneNoteModifiable(ExpenseStatus $status, bool $missing): void
    {
        $team = $this->createTeam();
        $this->setPolicy('hebergement', 15000, true);
        $expense = $this->createExpense($team['sophie'], 'Hôtel', 12000, '2026-10-01', 'hebergement', $status);

        $check = $this->checker()->check($expense);

        self::assertTrue($check->receiptRequired);
        self::assertSame($missing, $check->receiptMissing);
    }
}
