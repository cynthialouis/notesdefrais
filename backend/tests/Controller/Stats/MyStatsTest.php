<?php

namespace App\Tests\Controller\Stats;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * GET /api/stats/me (connecté) : ses propres notes par état, toutes dates confondues — spec 006 (C1, C2, C5).
 * Réponse : { byStatus: [ { status, count, totalCents } ] }, exactement 4 entrées dans l'ordre draft, submitted, approved, rejected.
 */
final class MyStatsTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('Europe/Paris')));
    }

    /**
     * @return list<array<mixed>>
     */
    private function byStatus(): array
    {
        self::assertResponseStatusCodeSame(200);
        $data = $this->responseJson();
        self::assertSame(['byStatus'], array_keys($data));
        self::assertIsArray($data['byStatus']);
        self::assertTrue(array_is_list($data['byStatus']));
        foreach ($data['byStatus'] as $entry) {
            self::assertIsArray($entry);
            self::assertSame(['status', 'count', 'totalCents'], array_keys($entry));
            // C5 : totaux calculés côté serveur, en centimes entiers
            self::assertIsInt($entry['count']);
            self::assertIsInt($entry['totalCents']);
        }

        return $data['byStatus'];
    }

    public function testC1LeSalarieVoitLeNombreEtLeTotalDeSesNotesParEtat(): void
    {
        $team = $this->createTeam();
        $sophie = $team['sophie'];
        $this->createExpense($sophie, 'Brouillon 1', 1250);
        $this->createExpense($sophie, 'Brouillon 2', 750, '2026-09-15', 'transport');
        $this->createExpense($sophie, 'Soumise', 4300, '2026-10-02', 'repas', ExpenseStatus::Submitted, '2026-10-03T09:00:00+02:00');
        $this->createApprovedExpense($sophie, $team['marc'], 'Validée 1', 10000, '2026-08-01');
        $this->createApprovedExpense($sophie, $team['marc'], 'Validée 2', 2599, '2026-09-01', 'hebergement');
        $this->createApprovedExpense($sophie, $team['marc'], 'Validée 3', 1, '2026-09-02', 'autre');
        $this->createExpense($sophie, 'Rejetée', 999, '2026-07-01', 'materiel', ExpenseStatus::Rejected, '2026-07-02T09:00:00+02:00');
        $this->login($sophie);

        $this->client->request('GET', '/api/stats/me');

        self::assertSame([
            ['status' => 'draft', 'count' => 2, 'totalCents' => 2000],
            ['status' => 'submitted', 'count' => 1, 'totalCents' => 4300],
            ['status' => 'approved', 'count' => 3, 'totalCents' => 12600],
            ['status' => 'rejected', 'count' => 1, 'totalCents' => 999],
        ], $this->byStatus());
    }

    public function testC1SeulesSesPropresNotesSontComptees(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['sophie'], 'Note Sophie', 1000);
        // Jamais : notes d'un collègue, de son manager, d'une autre équipe
        $this->createExpense($team['tom'], 'Note Tom', 2000);
        $this->createExpense($team['marc'], 'Note Marc', 3000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $this->createApprovedExpense($team['ines'], $team['julie'], 'Note Inès', 4000);
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/stats/me');

        self::assertSame([
            ['status' => 'draft', 'count' => 1, 'totalCents' => 1000],
            ['status' => 'submitted', 'count' => 0, 'totalCents' => 0],
            ['status' => 'approved', 'count' => 0, 'totalCents' => 0],
            ['status' => 'rejected', 'count' => 0, 'totalCents' => 0],
        ], $this->byStatus());
    }

    public function testC1LeManagerVoitSesPropresNotesEtPasCellesDeSonEquipe(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['marc'], 'Train séminaire', 14500, '2026-10-01', 'transport', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $this->createApprovedExpense($team['marc'], $team['admin'], 'Hôtel séminaire', 12000, '2026-09-10', 'hebergement');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Note Sophie', 5000);
        $this->createExpense($team['tom'], 'Note Tom', 2000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $this->login($team['marc']);

        $this->client->request('GET', '/api/stats/me');

        self::assertSame([
            ['status' => 'draft', 'count' => 0, 'totalCents' => 0],
            ['status' => 'submitted', 'count' => 1, 'totalCents' => 14500],
            ['status' => 'approved', 'count' => 1, 'totalCents' => 12000],
            ['status' => 'rejected', 'count' => 0, 'totalCents' => 0],
        ], $this->byStatus());
    }

    public function testC1LAdminVoitSesPropresNotes(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['admin'], 'Brouillon admin', 3333);
        $this->createApprovedExpense($team['marc'], $team['admin'], 'Note Marc', 5000);
        $this->login($team['admin']);

        $this->client->request('GET', '/api/stats/me');

        self::assertSame([
            ['status' => 'draft', 'count' => 1, 'totalCents' => 3333],
            ['status' => 'submitted', 'count' => 0, 'totalCents' => 0],
            ['status' => 'approved', 'count' => 0, 'totalCents' => 0],
            ['status' => 'rejected', 'count' => 0, 'totalCents' => 0],
        ], $this->byStatus());
    }

    public function testC1LesTotauxPersonnelsPortentSurToutesLesDates(): void
    {
        $team = $this->createTeam();
        // Hors de la période de 12 mois de l'équipe : comptées quand même pour ses propres totaux
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Ancienne', 7000, '2023-03-15');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Récente', 3000, '2026-10-01');
        $this->createExpense($team['sophie'], 'Brouillon futur dans le mois', 500, '2026-10-31');
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/stats/me');

        $byStatus = $this->byStatus();
        self::assertSame(['status' => 'approved', 'count' => 2, 'totalCents' => 10000], $byStatus[2]);
        self::assertSame(['status' => 'draft', 'count' => 1, 'totalCents' => 500], $byStatus[0]);
    }

    public function testC2UnUtilisateurSansNoteVoitLesQuatreEtatsAZero(): void
    {
        $sophie = $this->createUser('Sophie Seule', 'seule@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->client->request('GET', '/api/stats/me');

        self::assertSame([
            ['status' => 'draft', 'count' => 0, 'totalCents' => 0],
            ['status' => 'submitted', 'count' => 0, 'totalCents' => 0],
            ['status' => 'approved', 'count' => 0, 'totalCents' => 0],
            ['status' => 'rejected', 'count' => 0, 'totalCents' => 0],
        ], $this->byStatus());
    }

    public function testC2LesEtatsSansNoteRestentPresentsDansLOrdre(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['tom'], 'Rejetée', 4200, '2026-09-01', 'repas', ExpenseStatus::Rejected, '2026-09-02T09:00:00+02:00');
        $this->login($team['tom']);

        $this->client->request('GET', '/api/stats/me');

        $byStatus = $this->byStatus();
        self::assertSame(['draft', 'submitted', 'approved', 'rejected'], array_column($byStatus, 'status'));
        self::assertSame([0, 0, 0, 1], array_column($byStatus, 'count'));
        self::assertSame([0, 0, 0, 4200], array_column($byStatus, 'totalCents'));
    }

    public function testC5LesTotauxSontDesSommesExactesEnCentimes(): void
    {
        $team = $this->createTeam();
        // 0,10 + 0,20 n'est pas exact en flottant : en centimes entiers, 10 + 20 = 30
        $this->createExpense($team['sophie'], 'Dix centimes', 10);
        $this->createExpense($team['sophie'], 'Vingt centimes', 20);
        $this->createExpense($team['sophie'], 'Gros montant', 99999999);
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/stats/me');

        self::assertSame(['status' => 'draft', 'count' => 3, 'totalCents' => 100000029], $this->byStatus()[0]);
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $sophie = $this->createUser('Sophie', 'sophie@demo.test', UserRole::Employee);
        $this->createExpense($sophie, 'Note', 1000);

        $this->client->request('GET', '/api/stats/me');

        $this->assertErrorResponse(401);
        self::assertStringNotContainsString('byStatus', $this->responseBody());
    }
}
