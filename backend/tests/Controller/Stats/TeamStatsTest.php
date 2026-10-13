<?php

namespace App\Tests\Controller\Stats;

use App\Entity\Category;
use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Repository\CategoryRepository;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * GET /api/stats/team (manager et plus) : notes validées des subordonnés directs sur les 12 derniers mois civils,
 * mois en cours compris, d'après la date de la dépense, en heure de Paris — spec 006 (C3, C4, C5, C8, C9).
 * Réponse : { from, to, count, totalCents, byCategory: [ { category: { slug, label }, count, totalCents } ],
 * byMonth: [ { month, count, totalCents } ] }.
 */
final class TeamStatsTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const MONTHS_2026_10 = ['2025-11', '2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10'];

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('Europe/Paris')));
    }

    /**
     * Vérifie la forme de la réponse et la renvoie.
     *
     * @return array<mixed>
     */
    private function teamStats(): array
    {
        self::assertResponseStatusCodeSame(200);
        $data = $this->responseJson();
        self::assertSame(['from', 'to', 'count', 'totalCents', 'byCategory', 'byMonth'], array_keys($data));
        self::assertIsString($data['from']);
        self::assertIsString($data['to']);
        // C5 : totaux en centimes entiers
        self::assertIsInt($data['count']);
        self::assertIsInt($data['totalCents']);
        self::assertIsArray($data['byCategory']);
        self::assertTrue(array_is_list($data['byCategory']));
        foreach ($data['byCategory'] as $entry) {
            self::assertIsArray($entry);
            self::assertSame(['category', 'count', 'totalCents'], array_keys($entry));
            self::assertIsArray($entry['category']);
            self::assertSame(['slug', 'label'], array_keys($entry['category']));
            self::assertIsInt($entry['count']);
            self::assertIsInt($entry['totalCents']);
        }
        self::assertIsArray($data['byMonth']);
        self::assertTrue(array_is_list($data['byMonth']));
        foreach ($data['byMonth'] as $entry) {
            self::assertIsArray($entry);
            self::assertSame(['month', 'count', 'totalCents'], array_keys($entry));
            self::assertIsString($entry['month']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $entry['month']);
            self::assertIsInt($entry['count']);
            self::assertIsInt($entry['totalCents']);
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, array{count: int, totalCents: int}>
     */
    private function byCategorySlug(array $data): array
    {
        $result = [];
        foreach ($data['byCategory'] as $entry) {
            $result[$entry['category']['slug']] = ['count' => $entry['count'], 'totalCents' => $entry['totalCents']];
        }

        return $result;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, array{count: int, totalCents: int}>
     */
    private function byMonth(array $data): array
    {
        $result = [];
        foreach ($data['byMonth'] as $entry) {
            $result[$entry['month']] = ['count' => $entry['count'], 'totalCents' => $entry['totalCents']];
        }

        return $result;
    }

    // --- C3 : par catégorie -------------------------------------------------------------------

    public function testC3LeManagerVoitLeTotalDesNotesValideesDeSonEquipeParCategorie(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Déjeuner', 2500, '2026-10-01', 'repas');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Dîner', 3150, '2026-09-12', 'repas');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Hôtel', 12900, '2026-06-03', 'hebergement');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(3, $data['count']);
        self::assertSame(18550, $data['totalCents']);
        self::assertSame([
            'repas' => ['count' => 2, 'totalCents' => 5650],
            'transport' => ['count' => 0, 'totalCents' => 0],
            'hebergement' => ['count' => 1, 'totalCents' => 12900],
            'materiel' => ['count' => 0, 'totalCents' => 0],
            'autre' => ['count' => 0, 'totalCents' => 0],
        ], $this->byCategorySlug($data));
    }

    public function testC3ToutesLesCategoriesSontPresentesDansLOrdreDeReference(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Clavier', 3999, '2026-10-01', 'materiel');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        $expectedOrder = array_map(
            static fn (Category $category): string => $category->getSlug(),
            static::getContainer()->get(CategoryRepository::class)->findAllOrdered(),
        );
        self::assertEqualsCanonicalizing(['repas', 'transport', 'hebergement', 'materiel', 'autre'], $expectedOrder);
        self::assertSame($expectedOrder, array_map(static fn (array $entry): string => $entry['category']['slug'], $data['byCategory']));
        $labels = ['repas' => 'Repas', 'transport' => 'Transport', 'hebergement' => 'Hébergement', 'materiel' => 'Matériel', 'autre' => 'Autre'];
        self::assertSame(
            array_map(static fn (string $slug): array => ['slug' => $slug, 'label' => $labels[$slug]], $expectedOrder),
            array_column($data['byCategory'], 'category'),
        );
        self::assertSame(['count' => 1, 'totalCents' => 3999], $this->byCategorySlug($data)['materiel']);
    }

    public function testC3SeulesLesNotesValideesSontComptees(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Validée', 1000, '2026-10-01');
        $this->createExpense($team['sophie'], 'Brouillon', 2000, '2026-10-01');
        $this->createExpense($team['sophie'], 'Soumise', 4000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $this->createExpense($team['tom'], 'Rejetée', 8000, '2026-10-01', 'repas', ExpenseStatus::Rejected, '2026-10-02T09:00:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(1, $data['count']);
        self::assertSame(1000, $data['totalCents']);
        self::assertSame(['count' => 1, 'totalCents' => 1000], $this->byCategorySlug($data)['repas']);
        self::assertSame(['count' => 1, 'totalCents' => 1000], $this->byMonth($data)['2026-10']);
    }

    public function testC3LaPeriodeEstRenvoyeeAvecSesBornesIncluses(): void
    {
        $team = $this->createTeam();
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame('2025-11-01', $data['from']);
        self::assertSame('2026-10-31', $data['to']);
    }

    // --- C4 : par mois, période -----------------------------------------------------------------

    public function testC4DouzeMoisDuPlusAncienAuPlusRecentAvecLesMoisVidesAZero(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Novembre', 1100, '2025-11-20');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Mars A', 300, '2026-03-02', 'transport');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Mars B', 450, '2026-03-30', 'autre');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Octobre', 9900, '2026-10-12', 'materiel');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(self::MONTHS_2026_10, array_column($data['byMonth'], 'month'));
        $expected = [];
        foreach (self::MONTHS_2026_10 as $month) {
            $expected[$month] = ['count' => 0, 'totalCents' => 0];
        }
        $expected['2025-11'] = ['count' => 1, 'totalCents' => 1100];
        $expected['2026-03'] = ['count' => 2, 'totalCents' => 750];
        $expected['2026-10'] = ['count' => 1, 'totalCents' => 9900];
        self::assertSame($expected, $this->byMonth($data));
        self::assertSame(4, $data['count']);
        self::assertSame(11750, $data['totalCents']);
    }

    public function testC4BornesDeLaPeriode(): void
    {
        $team = $this->createTeam();
        // Inclus : premier jour du mois le plus ancien, dernier jour du mois en cours
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Premier jour', 100, '2025-11-01');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Dernier jour', 200, '2026-10-31');
        // Exclus : dernier jour du mois précédent la période, premier jour du mois suivant
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Trop ancien', 400, '2025-10-31');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Mois suivant', 800, '2026-11-01');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(2, $data['count']);
        self::assertSame(300, $data['totalCents']);
        $byMonth = $this->byMonth($data);
        self::assertSame(['count' => 1, 'totalCents' => 100], $byMonth['2025-11']);
        self::assertSame(['count' => 1, 'totalCents' => 200], $byMonth['2026-10']);
        self::assertArrayNotHasKey('2025-10', $byMonth);
        self::assertArrayNotHasKey('2026-11', $byMonth);
    }

    public function testC4LaPeriodeSuitLaDateDeLaDepenseEtNonLaDateDeValidation(): void
    {
        $team = $this->createTeam();
        // Dépense ancienne validée récemment : hors période
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Ancienne validée hier', 5000, '2025-09-15', 'repas', '2026-10-12T10:00:00+02:00');
        // Dépense dans la période validée il y a longtemps (cas théorique) : comptée dans le mois de la dépense
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Novembre', 700, '2025-11-05', 'repas', '2025-10-01T10:00:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(1, $data['count']);
        self::assertSame(700, $data['totalCents']);
        self::assertSame(['count' => 1, 'totalCents' => 700], $this->byMonth($data)['2025-11']);
    }

    public function testC4LaPeriodeChangeDAnneeEnJanvier(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-01-15 09:00:00', new \DateTimeZone('Europe/Paris')));
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Février', 100, '2025-02-01');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Décembre', 200, '2025-12-31');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Janvier', 400, '2026-01-31');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Janvier précédent', 800, '2025-01-31');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame('2025-02-01', $data['from']);
        self::assertSame('2026-01-31', $data['to']);
        self::assertSame(
            ['2025-02', '2025-03', '2025-04', '2025-05', '2025-06', '2025-07', '2025-08', '2025-09', '2025-10', '2025-11', '2025-12', '2026-01'],
            array_column($data['byMonth'], 'month'),
        );
        self::assertSame(3, $data['count']);
        self::assertSame(700, $data['totalCents']);
        $byMonth = $this->byMonth($data);
        self::assertSame(['count' => 1, 'totalCents' => 100], $byMonth['2025-02']);
        self::assertSame(['count' => 1, 'totalCents' => 200], $byMonth['2025-12']);
        self::assertSame(['count' => 1, 'totalCents' => 400], $byMonth['2026-01']);
    }

    public function testC4LaPeriodeEstCalculeeEnHeureDeParis(): void
    {
        // 31/10/2026 23:30 UTC = 01/11/2026 00:30 à Paris : le mois en cours est novembre
        self::mockTime(new \DateTimeImmutable('2026-10-31 23:30:00', new \DateTimeZone('UTC')));
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Novembre 2025', 100, '2025-11-30');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Décembre 2025', 200, '2025-12-01');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Novembre 2026', 400, '2026-11-01');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame('2025-12-01', $data['from']);
        self::assertSame('2026-11-30', $data['to']);
        self::assertSame('2025-12', $data['byMonth'][0]['month']);
        self::assertSame('2026-11', $data['byMonth'][11]['month']);
        self::assertSame(2, $data['count']);
        self::assertSame(600, $data['totalCents']);
    }

    // --- C5 : centimes entiers ------------------------------------------------------------------

    public function testC5LesTotauxDEquipeSontDesSommesExactesEnCentimes(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Dix', 10, '2026-10-01');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Vingt', 20, '2026-10-02');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Gros', 99999999, '2026-10-03');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(100000029, $data['totalCents']);
        self::assertSame(['count' => 3, 'totalCents' => 100000029], $this->byCategorySlug($data)['repas']);
        self::assertSame(['count' => 3, 'totalCents' => 100000029], $this->byMonth($data)['2026-10']);
    }

    // --- C8 : équipe directe seulement ----------------------------------------------------------

    public function testC8ExclutLesNotesDuManagerDUneAutreEquipeEtDesSubordonnesIndirects(): void
    {
        $team = $this->createTeam();
        // Subordonné indirect de Marc (rattaché à Sophie)
        $stagiaire = $this->createUser('Léo Stagiaire', 'leo@demo.test', UserRole::Employee, $team['sophie']);
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Sophie', 1000, '2026-10-01');
        $this->createApprovedExpense($team['marc'], $team['admin'], 'Marc lui-même', 20000, '2026-10-01');
        $this->createApprovedExpense($team['ines'], $team['julie'], 'Autre équipe', 40000, '2026-10-01');
        $this->createApprovedExpense($team['julie'], $team['admin'], 'Autre manager', 80000, '2026-10-01');
        $this->createApprovedExpense($stagiaire, $team['sophie'], 'Indirect', 160000, '2026-10-01');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(1, $data['count']);
        self::assertSame(1000, $data['totalCents']);
        self::assertSame(['count' => 1, 'totalCents' => 1000], $this->byCategorySlug($data)['repas']);
        self::assertSame(['count' => 1, 'totalCents' => 1000], $this->byMonth($data)['2026-10']);
    }

    public function testC8LAdminVoitLesNotesValideesDesManagersEtPasCellesDeLeursSalaries(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['marc'], $team['admin'], 'Train Marc', 14500, '2026-09-10', 'transport');
        $this->createApprovedExpense($team['julie'], $team['admin'], 'Hôtel Julie', 12000, '2026-08-05', 'hebergement');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Salariée de Marc', 999, '2026-09-10');
        $this->createApprovedExpense($team['ines'], $team['julie'], 'Salariée de Julie', 888, '2026-09-10');
        $this->createApprovedExpense($team['admin'], $team['admin'], 'Admin elle-même', 777, '2026-09-10');
        $this->login($team['admin']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(2, $data['count']);
        self::assertSame(26500, $data['totalCents']);
        $byCategory = $this->byCategorySlug($data);
        self::assertSame(['count' => 1, 'totalCents' => 14500], $byCategory['transport']);
        self::assertSame(['count' => 1, 'totalCents' => 12000], $byCategory['hebergement']);
        self::assertSame(['count' => 0, 'totalCents' => 0], $byCategory['repas']);
        $byMonth = $this->byMonth($data);
        self::assertSame(['count' => 1, 'totalCents' => 14500], $byMonth['2026-09']);
        self::assertSame(['count' => 1, 'totalCents' => 12000], $byMonth['2026-08']);
    }

    // --- C9 : équipe sans note validée sur la période -------------------------------------------

    public function testC9SansNoteValideeSurLaPeriodeTousLesTotauxSontAZero(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['sophie'], 'Soumise', 4000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Trop ancienne', 5000, '2024-06-01');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(0, $data['count']);
        self::assertSame(0, $data['totalCents']);
        self::assertCount(5, $data['byCategory']);
        self::assertSame([0, 0, 0, 0, 0], array_column($data['byCategory'], 'totalCents'));
        self::assertSame(self::MONTHS_2026_10, array_column($data['byMonth'], 'month'));
        self::assertSame(array_fill(0, 12, 0), array_column($data['byMonth'], 'count'));
        self::assertSame(array_fill(0, 12, 0), array_column($data['byMonth'], 'totalCents'));
    }

    public function testC9UnManagerSansEquipeRecoitDesTotauxAZero(): void
    {
        $manager = $this->createUser('Manager seul', 'seul@demo.test', UserRole::Manager);
        $this->login($manager);

        $this->client->request('GET', '/api/stats/team');

        $data = $this->teamStats();
        self::assertSame(0, $data['count']);
        self::assertSame(0, $data['totalCents']);
        self::assertCount(12, $data['byMonth']);
        self::assertCount(5, $data['byCategory']);
    }

    // --- Autorisations ----------------------------------------------------------------------------

    public function testAutorisationLeSalarieRecoit403(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Note', 1000, '2026-10-01');
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/stats/team');

        $this->assertErrorResponse(403);
        self::assertStringNotContainsString('totalCents', $this->responseBody());
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $this->client->request('GET', '/api/stats/team');

        $this->assertErrorResponse(401);
        self::assertStringNotContainsString('totalCents', $this->responseBody());
    }
}
