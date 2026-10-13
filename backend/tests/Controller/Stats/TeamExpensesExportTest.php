<?php

namespace App\Tests\Controller\Stats;

use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * GET /api/exports/team-expenses.csv (manager et plus) : notes validées de l'équipe directe sur la période,
 * au format d'un tableur français — spec 006 (C6, C7, C8, C9, cas limites d'échappement et de formules).
 */
final class TeamExpensesExportTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const BOM = "\xEF\xBB\xBF";
    private const HEADER = 'Date;Salarié;Libellé;Catégorie;Montant;Date de validation;Validée par';

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(new \DateTimeImmutable('2026-10-13 12:00:00', new \DateTimeZone('Europe/Paris')));
    }

    private function export(): void
    {
        $this->client->request('GET', '/api/exports/team-expenses.csv');
    }

    /**
     * Vérifie le succès et le BOM, et renvoie le corps sans le BOM.
     */
    private function csvWithoutBom(): string
    {
        self::assertResponseStatusCodeSame(200);
        $body = $this->responseBody();
        self::assertStringStartsWith(self::BOM, $body, 'Le CSV doit commencer par le BOM UTF-8.');
        $csv = substr($body, \strlen(self::BOM));
        self::assertTrue(mb_check_encoding($csv, 'UTF-8'), 'Le CSV doit être encodé en UTF-8.');

        return $csv;
    }

    /**
     * Lignes de données (sans l'en-tête), en champs.
     *
     * @return list<list<string>>
     */
    private function dataRows(): array
    {
        $rows = $this->parseCsv($this->csvWithoutBom());
        self::assertNotEmpty($rows);
        self::assertSame(explode(';', self::HEADER), $rows[0]);

        return \array_slice($rows, 1);
    }

    // --- C6 : contenu ---------------------------------------------------------------------------

    public function testC6LExportContientUneLigneParNoteValideeDeLEquipeAvecSesColonnes(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Déjeuner client', 2550, '2026-09-14', 'repas', '2026-09-20T10:15:00+02:00');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Hôtel salon', 12800, '2026-03-02', 'hebergement', '2026-03-10T16:00:00+01:00');
        $this->login($team['marc']);

        $this->export();

        self::assertSame([
            ['02/03/2026', 'Tom Salarié', 'Hôtel salon', 'Hébergement', '128,00', '10/03/2026', 'Marc Manager'],
            ['14/09/2026', 'Sophie Salariée', 'Déjeuner client', 'Repas', '25,50', '20/09/2026', 'Marc Manager'],
        ], $this->dataRows());
    }

    public function testC6LeCorpsExactAvecBomEnTeteEtFinsDeLigneCrlf(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Taxi gare', 2350, '2026-10-02', 'transport', '2026-10-05T10:00:00+02:00');
        $this->login($team['marc']);

        $this->export();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            self::BOM.self::HEADER."\r\n".'02/10/2026;Sophie Salariée;Taxi gare;Transport;23,50;05/10/2026;Marc Manager'."\r\n",
            $this->responseBody(),
        );
    }

    public function testC6LesLignesSontTrieesParDateDeDepensePuisId(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'C', 300, '2026-08-10');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'A', 100, '2026-05-01');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'D', 400, '2026-08-10');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'B', 200, '2026-07-20');
        $this->login($team['marc']);

        $this->export();

        self::assertSame(['A', 'B', 'C', 'D'], array_column($this->dataRows(), 2));
    }

    public function testC6SeulesLesNotesValideesDeLaPeriodeSontExportees(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Premier jour', 100, '2025-11-01');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Dernier jour', 200, '2026-10-31');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Trop ancienne', 400, '2025-10-31');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Mois suivant', 800, '2026-11-01');
        $this->createExpense($team['sophie'], 'Brouillon', 1600, '2026-10-01');
        $this->createExpense($team['tom'], 'Soumise', 3200, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $this->createExpense($team['tom'], 'Rejetée', 6400, '2026-10-01', 'repas', ExpenseStatus::Rejected, '2026-10-02T09:00:00+02:00');
        $this->login($team['marc']);

        $this->export();

        self::assertSame(['Premier jour', 'Dernier jour'], array_column($this->dataRows(), 2));
    }

    public function testC6LesEnTetesHttpDeTelechargement(): void
    {
        $team = $this->createTeam();
        $this->login($team['marc']);

        $this->export();

        self::assertResponseStatusCodeSame(200);
        $response = $this->client->getResponse();
        $contentType = (string) $response->headers->get('Content-Type');
        self::assertStringStartsWith('text/csv', $contentType);
        self::assertStringContainsStringIgnoringCase('charset=UTF-8', $contentType);
        self::assertSame('attachment; filename="notes-validees-equipe-2026-10-13.csv"', $response->headers->get('Content-Disposition'));
    }

    public function testC6LeNomDuFichierPorteLaDateDuJourAParis(): void
    {
        // 31/10/2026 23:30 UTC = 01/11/2026 00:30 à Paris
        self::mockTime(new \DateTimeImmutable('2026-10-31 23:30:00', new \DateTimeZone('UTC')));
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Novembre 2025', 100, '2025-11-30');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Novembre 2026', 200, '2026-11-01');
        $this->login($team['marc']);

        $this->export();

        self::assertSame('attachment; filename="notes-validees-equipe-2026-11-01.csv"', $this->client->getResponse()->headers->get('Content-Disposition'));
        // Période de Paris : décembre 2025 à novembre 2026
        self::assertSame(['Novembre 2026'], array_column($this->dataRows(), 2));
    }

    // --- C7 : format tableur français -----------------------------------------------------------

    public function testC7MontantsEnEurosAvecVirguleEtDeuxDecimalesSansSeparateurDeMilliers(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Un', 5, '2026-10-01');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Deux', 1200, '2026-10-02');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Trois', 123450, '2026-10-03');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Quatre', 100000000, '2026-10-04');
        $this->login($team['marc']);

        $this->export();

        self::assertSame(['0,05', '12,00', '1234,50', '1000000,00'], array_column($this->dataRows(), 4));
    }

    public function testC7DatesAuFormatFrancaisEtDateDeValidationEnHeureDeParis(): void
    {
        $team = $this->createTeam();
        // Validée le 30/09 à 23:30 UTC, soit le 01/10 à 01:30 à Paris
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Note', 1000, '2026-09-05', 'repas', '2026-09-30T23:30:00+00:00');
        $this->login($team['marc']);

        $this->export();

        $rows = $this->dataRows();
        self::assertCount(1, $rows);
        self::assertSame('05/09/2026', $rows[0][0]);
        self::assertSame('01/10/2026', $rows[0][5]);
    }

    public function testC7ValideeParEstLeNomDuDecideur(): void
    {
        $team = $this->createTeam();
        // Marc absent : c'est l'admin qui a validé (cas théorique), le nom affiché est celui du décideur réel
        $this->createApprovedExpense($team['sophie'], $team['admin'], 'Note', 1000, '2026-10-01');
        $this->login($team['marc']);

        $this->export();

        self::assertSame('Alice Admin', $this->dataRows()[0][6]);
    }

    public function testC7CasLimiteUnLibelleAvecPointVirguleGuillemetOuRetourALaLigneEstEchappe(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Taxi; gare', 100, '2026-10-01');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Hôtel "Le Lac"', 200, '2026-10-02');
        $this->createApprovedExpense($team['sophie'], $team['marc'], "Repas\nclient", 300, '2026-10-03');
        $this->createApprovedExpense($team['sophie'], $team['marc'], "Train\r\nretour", 400, '2026-10-04');
        $this->login($team['marc']);

        $this->export();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            self::BOM.self::HEADER."\r\n"
            .'01/10/2026;Sophie Salariée;"Taxi; gare";Repas;1,00;05/10/2026;Marc Manager'."\r\n"
            .'02/10/2026;Sophie Salariée;"Hôtel ""Le Lac""";Repas;2,00;05/10/2026;Marc Manager'."\r\n"
            ."03/10/2026;Sophie Salariée;\"Repas\nclient\";Repas;3,00;05/10/2026;Marc Manager\r\n"
            ."04/10/2026;Sophie Salariée;\"Train\r\nretour\";Repas;4,00;05/10/2026;Marc Manager\r\n",
            $this->responseBody(),
        );
        // Une ligne par note, malgré les séparateurs dans les libellés
        $rows = $this->dataRows();
        self::assertCount(4, $rows);
        self::assertSame(['Taxi; gare', 'Hôtel "Le Lac"', "Repas\nclient", "Train\r\nretour"], array_column($rows, 2));
        foreach ($rows as $row) {
            self::assertCount(7, $row);
        }
    }

    public function testC7CasLimiteUnLibelleCommencantParUnCaractereDeFormuleEstNeutralise(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], '=SOMME(A1:A9)', 100, '2026-10-01');
        $this->createApprovedExpense($team['sophie'], $team['marc'], '+33 taxi', 200, '2026-10-02');
        $this->createApprovedExpense($team['sophie'], $team['marc'], '-5 remise', 300, '2026-10-03');
        $this->createApprovedExpense($team['sophie'], $team['marc'], '@cmd', 400, '2026-10-04');
        $this->createApprovedExpense($team['sophie'], $team['marc'], "\tTabulation", 500, '2026-10-05');
        $this->createApprovedExpense($team['sophie'], $team['marc'], "\rRetour", 600, '2026-10-06');
        $this->createApprovedExpense($team['sophie'], $team['marc'], '=1;2', 700, '2026-10-07');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Taxi = gare - aller', 800, '2026-10-08');
        $this->login($team['marc']);

        $this->export();

        self::assertResponseStatusCodeSame(200);
        $lines = explode("\r\n", $this->responseBody());
        // Apostrophe ajoutée avant l'échappement : un champ qui contient « ; » ou \r reste entre guillemets
        self::assertSame("01/10/2026;Sophie Salariée;'=SOMME(A1:A9);Repas;1,00;05/10/2026;Marc Manager", $lines[1]);
        self::assertSame("02/10/2026;Sophie Salariée;'+33 taxi;Repas;2,00;05/10/2026;Marc Manager", $lines[2]);
        self::assertSame("03/10/2026;Sophie Salariée;'-5 remise;Repas;3,00;05/10/2026;Marc Manager", $lines[3]);
        self::assertSame("04/10/2026;Sophie Salariée;'@cmd;Repas;4,00;05/10/2026;Marc Manager", $lines[4]);
        self::assertSame("05/10/2026;Sophie Salariée;'\tTabulation;Repas;5,00;05/10/2026;Marc Manager", $lines[5]);
        self::assertSame("06/10/2026;Sophie Salariée;\"'\rRetour\";Repas;6,00;05/10/2026;Marc Manager", $lines[6]);
        self::assertSame("07/10/2026;Sophie Salariée;\"'=1;2\";Repas;7,00;05/10/2026;Marc Manager", $lines[7]);
        // Un caractère de formule ailleurs qu'au début ne change rien
        self::assertSame('08/10/2026;Sophie Salariée;Taxi = gare - aller;Repas;8,00;05/10/2026;Marc Manager', $lines[8]);
        // En-tête, 8 lignes, puis la chaîne vide qui suit le \r\n final
        self::assertCount(10, $lines);
        self::assertSame('', $lines[9]);
    }

    public function testC7CasLimiteLaNeutralisationSAppliqueATousLesChamps(): void
    {
        $team = $this->createTeam();
        $piege = $this->createUser('=HYPERLINK("http://x")', 'piege@demo.test', UserRole::Employee, $team['marc']);
        $decideur = $this->createUser('@Décideur', 'decideur@demo.test', UserRole::Manager, $team['admin']);
        $this->createApprovedExpense($piege, $decideur, 'Note', 1000, '2026-10-01');
        $this->login($team['marc']);

        $this->export();

        $rows = $this->dataRows();
        self::assertCount(1, $rows);
        self::assertSame('\'=HYPERLINK("http://x")', $rows[0][1]);
        self::assertSame('\'@Décideur', $rows[0][6]);
        self::assertStringContainsString(';"\'=HYPERLINK(""http://x"")";', $this->responseBody());
    }

    // --- C8 : équipe directe seulement ----------------------------------------------------------

    public function testC8LExportExclutLeManagerUneAutreEquipeEtLesSubordonnesIndirects(): void
    {
        $team = $this->createTeam();
        $stagiaire = $this->createUser('Léo Stagiaire', 'leo@demo.test', UserRole::Employee, $team['sophie']);
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Sophie', 1000, '2026-10-01');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Tom', 1000, '2026-10-02');
        $this->createApprovedExpense($team['marc'], $team['admin'], 'Marc lui-même', 1000, '2026-10-01');
        $this->createApprovedExpense($team['ines'], $team['julie'], 'Autre équipe', 1000, '2026-10-01');
        $this->createApprovedExpense($stagiaire, $team['sophie'], 'Indirect', 1000, '2026-10-01');
        $this->login($team['marc']);

        $this->export();

        self::assertSame(['Sophie', 'Tom'], array_column($this->dataRows(), 2));
    }

    public function testC8LAdminExporteLesNotesValideesDesManagers(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['marc'], $team['admin'], 'Train Marc', 14500, '2026-09-10', 'transport');
        $this->createApprovedExpense($team['julie'], $team['admin'], 'Hôtel Julie', 12000, '2026-08-05', 'hebergement');
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Salariée de Marc', 999, '2026-09-10');
        $this->createApprovedExpense($team['admin'], $team['admin'], 'Admin elle-même', 777, '2026-09-10');
        $this->login($team['admin']);

        $this->export();

        self::assertSame([
            ['05/08/2026', 'Julie Manager', 'Hôtel Julie', 'Hébergement', '120,00', '05/10/2026', 'Alice Admin'],
            ['10/09/2026', 'Marc Manager', 'Train Marc', 'Transport', '145,00', '05/10/2026', 'Alice Admin'],
        ], $this->dataRows());
    }

    // --- C9 : export vide -----------------------------------------------------------------------

    public function testC9SansNoteValideeLExportNeContientQueLaLigneDEnTete(): void
    {
        $team = $this->createTeam();
        $this->createExpense($team['sophie'], 'Soumise', 4000, '2026-10-01', 'repas', ExpenseStatus::Submitted, '2026-10-02T09:00:00+02:00');
        $this->createApprovedExpense($team['tom'], $team['marc'], 'Trop ancienne', 5000, '2024-06-01');
        $this->login($team['marc']);

        $this->export();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::BOM.self::HEADER."\r\n", $this->responseBody());
    }

    // --- Autorisations ----------------------------------------------------------------------------

    public function testAutorisationLeSalarieRecoit403(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Note secrète', 1000, '2026-10-01');
        $this->login($team['sophie']);

        $this->export();

        $this->assertErrorResponse(403);
        self::assertStringNotContainsString('Note secrète', $this->responseBody());
        self::assertStringNotContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $this->createApprovedExpense($team['sophie'], $team['marc'], 'Note secrète', 1000, '2026-10-01');

        $this->export();

        $this->assertErrorResponse(401);
        self::assertStringNotContainsString('Note secrète', $this->responseBody());
    }
}
