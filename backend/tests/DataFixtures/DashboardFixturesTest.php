<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Expense;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use App\Tests\Support\ApiTestCase;

/**
 * Données de démo du tableau de bord — spec 006 (plan, tâche 5 ; C3, C4) : l'équipe de Claire Dubois a des notes validées
 * sur au moins 3 mois et 2 catégories de la période, au moins une note validée hors période (absente des totaux),
 * et au moins un libellé avec « ; » ou un guillemet pour montrer l'échappement du CSV.
 *
 * Les fixtures datent leurs notes en relatif depuis l'heure réelle (« -N days ») et non depuis l'horloge :
 * le temps n'est pas figé ici, la période attendue est calculée depuis l'heure réelle, en heure de Paris.
 */
final class DashboardFixturesTest extends ApiTestCase
{
    private function loadDemo(): User
    {
        $fixtures = static::getContainer()->get(AppFixtures::class);
        self::assertInstanceOf(AppFixtures::class, $fixtures);
        $em = $this->entityManager();
        $fixtures->load($em);
        $em->clear();

        $claire = $em->getRepository(User::class)->findOneBy(['email' => 'claire.dubois@demo.test']);
        self::assertInstanceOf(User::class, $claire);

        return $claire;
    }

    /**
     * @return array{0: string, 1: string} premier et dernier jour de la période (AAAA-MM-JJ)
     */
    private function expectedPeriod(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        return [$now->modify('first day of this month')->modify('-11 months')->format('Y-m-d'), $now->modify('last day of this month')->format('Y-m-d')];
    }

    /**
     * Notes validées des subordonnés directs de Claire.
     *
     * @return list<Expense>
     */
    private function approvedTeamExpenses(User $claire): array
    {
        $expenses = $this->entityManager()->getRepository(Expense::class)->findBy(['status' => ExpenseStatus::Approved]);

        return array_values(array_filter(
            $expenses,
            static fn (Expense $expense): bool => $expense->getOwner()->getManager()?->getId() === $claire->getId(),
        ));
    }

    public function testC3C4LEquipeDeClaireADesNotesValideesSurAuMoinsTroisMoisEtDeuxCategories(): void
    {
        $claire = $this->loadDemo();
        $this->login($claire);

        $this->client->request('GET', '/api/stats/team');

        self::assertResponseStatusCodeSame(200);
        $data = $this->responseJson();
        [$from, $to] = $this->expectedPeriod();
        self::assertSame($from, $data['from'] ?? null);
        self::assertSame($to, $data['to'] ?? null);
        $months = array_filter($data['byMonth'], static fn (array $entry): bool => $entry['count'] > 0);
        self::assertGreaterThanOrEqual(3, \count($months), 'Notes validées attendues sur au moins 3 mois de la période.');
        $categories = array_filter($data['byCategory'], static fn (array $entry): bool => $entry['count'] > 0);
        self::assertGreaterThanOrEqual(2, \count($categories), 'Notes validées attendues dans au moins 2 catégories.');
    }

    public function testC4UneNoteValideeHorsPeriodeNApparaitPasDansLesTotaux(): void
    {
        $claire = $this->loadDemo();
        [$from, $to] = $this->expectedPeriod();

        $inPeriod = [];
        $outOfPeriod = [];
        foreach ($this->approvedTeamExpenses($claire) as $expense) {
            $date = $expense->getDate()->format('Y-m-d');
            if ($date >= $from && $date <= $to) {
                $inPeriod[] = $expense;
            } elseif ($date < $from) {
                $outOfPeriod[] = $expense;
            }
        }
        self::assertNotEmpty($outOfPeriod, 'La démo doit contenir une note validée de l\'équipe de Claire datée de plus de 12 mois.');

        $this->login($claire);
        $this->client->request('GET', '/api/stats/team');

        self::assertResponseStatusCodeSame(200);
        $data = $this->responseJson();
        self::assertSame(\count($inPeriod), $data['count']);
        self::assertSame(array_sum(array_map(static fn (Expense $expense): int => $expense->getAmountCents(), $inPeriod)), $data['totalCents']);
    }

    public function testC7UnLibelleDeNoteValideeContientUnPointVirguleOuUnGuillemet(): void
    {
        $claire = $this->loadDemo();
        [$from, $to] = $this->expectedPeriod();

        $tricky = array_values(array_filter(
            $this->approvedTeamExpenses($claire),
            static fn (Expense $expense): bool => (str_contains($expense->getLabel(), ';') || str_contains($expense->getLabel(), '"'))
                && $expense->getDate()->format('Y-m-d') >= $from
                && $expense->getDate()->format('Y-m-d') <= $to,
        ));
        self::assertNotEmpty($tricky, 'La démo doit contenir une note validée de l\'équipe de Claire, dans la période, dont le libellé contient « ; » ou un guillemet.');

        // Le libellé ressort intact de l'export, sur une seule ligne de note
        $this->login($claire);
        $this->client->request('GET', '/api/exports/team-expenses.csv');

        self::assertResponseStatusCodeSame(200);
        $body = $this->responseBody();
        self::assertStringStartsWith("\xEF\xBB\xBF", $body);
        $rows = $this->parseCsv(substr($body, 3));
        $labels = array_column(\array_slice($rows, 1), 2);
        self::assertContains($tricky[0]->getLabel(), $labels);
        foreach (\array_slice($rows, 1) as $row) {
            self::assertCount(7, $row);
        }
    }
}
