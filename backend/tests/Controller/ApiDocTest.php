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
        foreach (['UserView', 'DemoUserView', 'PersonRef', 'CategoryView', 'ExpenseCategoryRef', 'PolicyCheck', 'ExpenseView', 'ExpenseEventView'] as $schema) {
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
}
