<?php

namespace App\Tests\Controller;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Contrat OpenAPI des réponses : un champ nullable est toujours présent (null), il doit donc être
 * déclaré obligatoire, sinon le front généré le croit facultatif et ne détecte pas son absence.
 */
final class ApiDocTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function responseSchemas(): iterable
    {
        foreach (['UserView', 'DemoUserView', 'PersonRef', 'CategoryView', 'ExpenseCategoryRef', 'PolicyCheck', 'ExpenseView', 'ExpenseEventView', 'ReceiptView',
            // Spec 006 : tableau de bord
            'MyStatsView', 'StatusTotalView', 'TeamStatsView', 'CategoryTotalView', 'MonthTotalView',
            // Spec 007 : congés
            'LeaveRequestView'] as $schema) {
            yield $schema => [$schema];
        }
    }

    #[DataProvider('responseSchemas')]
    public function testTousLesChampsDUneReponseSontObligatoires(string $schema): void
    {
        $this->client->request('GET', '/api/doc.json');
        self::assertResponseIsSuccessful();

        $definition = $this->responseJson()['components']['schemas'][$schema] ?? null;
        self::assertIsArray($definition, 'Schéma absent du contrat : '.$schema);
        $properties = array_keys($definition['properties']);
        $required = $definition['required'] ?? [];
        sort($properties);
        sort($required);
        self::assertSame($properties, $required);
    }

    /**
     * Spec 005 : les trois routes du justificatif sont décrites dans le contrat.
     */
    public function testC5LesRoutesDuJustificatifSontDecrites(): void
    {
        $this->client->request('GET', '/api/doc.json');
        self::assertResponseIsSuccessful();

        $path = $this->responseJson()['paths']['/api/expenses/{id}/receipt'] ?? null;
        self::assertIsArray($path, 'Route /api/expenses/{id}/receipt absente du contrat.');
        foreach (['post', 'get', 'delete'] as $method) {
            self::assertArrayHasKey($method, $path, "Méthode $method absente du contrat.");
        }
        $expenseView = $this->responseJson()['components']['schemas']['ExpenseView']['properties'] ?? [];
        self::assertArrayHasKey('receipt', $expenseView);
    }

    /**
     * Spec 006 : les deux routes de statistiques et l'export CSV sont décrits dans le contrat.
     */
    public function testC1C3C6LesRoutesDuTableauDeBordSontDecrites(): void
    {
        $this->client->request('GET', '/api/doc.json');
        self::assertResponseIsSuccessful();

        $paths = $this->responseJson()['paths'] ?? [];
        foreach (['/api/stats/me', '/api/stats/team', '/api/exports/team-expenses.csv'] as $path) {
            self::assertArrayHasKey($path, $paths, "Route $path absente du contrat.");
            self::assertArrayHasKey('get', $paths[$path], "GET $path absent du contrat.");
        }
    }

    /**
     * Spec 007 : les routes des congés sont décrites dans le contrat, et la vue d'une demande porte tous ses champs.
     */
    public function testC1C6C15C18LesRoutesDesCongesSontDecrites(): void
    {
        $this->client->request('GET', '/api/doc.json');
        self::assertResponseIsSuccessful();

        $doc = $this->responseJson();
        $paths = $doc['paths'] ?? [];
        foreach ([
            '/api/leaves' => ['get', 'post'],
            '/api/leaves/pending' => ['get'],
            '/api/me/leaves' => ['get'],
            '/api/team/leaves' => ['get'],
            '/api/leaves/{id}/accept' => ['post'],
            '/api/leaves/{id}/refuse' => ['post'],
            '/api/leaves/{id}/withdraw' => ['post'],
            '/api/leaves/{id}/cancel' => ['post'],
        ] as $path => $methods) {
            self::assertArrayHasKey($path, $paths, "Route $path absente du contrat.");
            foreach ($methods as $method) {
                self::assertArrayHasKey($method, $paths[$path], "Méthode $method de $path absente du contrat.");
            }
        }

        $properties = array_keys($doc['components']['schemas']['LeaveRequestView']['properties'] ?? []);
        sort($properties);
        $expected = ['id', 'requester', 'startDate', 'endDate', 'status', 'createdAt', 'decidedAt', 'decidedBy', 'refusalReason',
            'cancelledAt', 'cancelledBy', 'cancellationReason', 'decidable', 'withdrawable', 'cancellable'];
        sort($expected);
        self::assertSame($expected, $properties);
    }
}
