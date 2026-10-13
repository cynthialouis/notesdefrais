<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Expense;
use App\Enum\ExpenseStatus;
use App\Service\PolicyChecker;
use App\Tests\Support\ApiTestCase;

/**
 * Justificatifs des données de démo — spec 005 (plan, C9, C10) : les notes soumises ou validées des catégories
 * à justificatif obligatoire en ont un (fichier présent dans le stockage), et au moins un brouillon d'hébergement
 * de Léa Bernard n'en a pas, pour montrer la soumission bloquée.
 * Hérite d'ApiTestCase pour le nettoyage du dossier de stockage entre les tests.
 */
final class ReceiptFixturesTest extends ApiTestCase
{
    /**
     * @return list<Expense>
     */
    private function loadDemo(): array
    {
        $fixtures = static::getContainer()->get(AppFixtures::class);
        self::assertInstanceOf(AppFixtures::class, $fixtures);
        $em = $this->entityManager();
        $fixtures->load($em);
        $em->clear();

        return $em->getRepository(Expense::class)->findAll();
    }

    public function testC9LesNotesSoumisesOuValideesAJustificatifObligatoireOntUnJustificatif(): void
    {
        $expenses = $this->loadDemo();

        $checked = 0;
        foreach ($expenses as $expense) {
            if (!$expense->getCategory()->isReceiptRequired() || !\in_array($expense->getStatus(), [ExpenseStatus::Submitted, ExpenseStatus::Approved], true)) {
                continue;
            }
            ++$checked;
            $receipt = $expense->getReceipt();
            self::assertNotNull($receipt, 'Justificatif manquant sur « '.$expense->getLabel().' ».');
            self::assertContains($receipt->getMimeType(), ['image/jpeg', 'image/png', 'application/pdf']);
            self::assertGreaterThan(0, $receipt->getSizeBytes());
            // Le fichier existe : le justificatif s'ouvre en démo (C5).
            $path = $this->receiptDir().'/'.$receipt->getStorageName();
            self::assertFileExists($path, 'Fichier absent pour « '.$expense->getLabel().' ».');
            self::assertSame($receipt->getSizeBytes(), filesize($path));
        }
        self::assertGreaterThan(0, $checked, 'La démo doit contenir au moins une note soumise ou validée à justificatif obligatoire.');
    }

    public function testC10LaDemoContientUnBrouillonDHebergementDeLeaSansJustificatif(): void
    {
        $expenses = $this->loadDemo();
        $checker = static::getContainer()->get(PolicyChecker::class);
        self::assertInstanceOf(PolicyChecker::class, $checker);

        $missing = array_filter(
            $expenses,
            static fn (Expense $expense): bool => 'lea.bernard@demo.test' === $expense->getOwner()->getEmail()
                && ExpenseStatus::Draft === $expense->getStatus()
                && 'hebergement' === $expense->getCategory()->getSlug()
                && null === $expense->getReceipt()
                && $checker->check($expense)->receiptMissing,
        );
        self::assertNotEmpty($missing, 'La démo doit contenir un brouillon d\'hébergement de Léa Bernard sans justificatif (C10).');
    }
}
