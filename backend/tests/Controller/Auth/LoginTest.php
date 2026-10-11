<?php

namespace App\Tests\Controller\Auth;

use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;

/**
 * POST /api/auth/login (public).
 */
final class LoginTest extends ApiTestCase
{
    public function testC2ConnexionOuvreUneSessionEtRenvoieLUtilisateur(): void
    {
        $manager = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $employee = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $manager);

        $this->login($employee);

        self::assertResponseStatusCodeSame(200);
        $body = $this->responseJson();
        self::assertSame($employee->getId(), $body['id'] ?? null);
        self::assertSame('Sophie Salariée', $body['name'] ?? null);
        self::assertSame('sophie@demo.test', $body['email'] ?? null);
        self::assertSame('employee', $body['role'] ?? null);
        self::assertSame(['employee'], $body['accessibleRoles'] ?? null);

        self::assertNotEmpty($this->client->getResponse()->headers->getCookies(), 'La connexion doit poser un cookie de session.');

        // La session est bien ouverte pour ce compte.
        $this->assertAuthenticatedAs($employee);
    }

    public function testC10ConnexionSansUserIdRenvoie400AvecLeChamp(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', []);

        $error = $this->assertErrorResponse(400);
        self::assertArrayHasKey('fields', $error);
        self::assertIsArray($error['fields']);
        self::assertArrayHasKey('userId', $error['fields']);
        $this->assertNotAuthenticated();
    }

    public function testC10ConnexionAvecUserIdNullRenvoie400AvecLeChamp(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['userId' => null]);

        $error = $this->assertErrorResponse(400);
        self::assertArrayHasKey('userId', $error['fields'] ?? []);
        $this->assertNotAuthenticated();
    }

    public function testC10ConnexionAvecUserIdNonEntierRenvoie400AvecLeChamp(): void
    {
        $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);

        $this->jsonRequest('POST', '/api/auth/login', ['userId' => 'abc']);

        $error = $this->assertErrorResponse(400);
        self::assertArrayHasKey('userId', $error['fields'] ?? []);
        $this->assertNotAuthenticated();
    }

    public function testC10ConnexionAvecCorpsJsonMalFormeRenvoie400(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', '{"userId":');

        $this->assertErrorResponse(400);
        $this->assertNotAuthenticated();
    }

    public function testC11ConnexionAvecCompteInexistantRenvoie401(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['userId' => 999999]);

        $error = $this->assertErrorResponse(401);
        self::assertArrayNotHasKey('fields', $error, 'Un compte inexistant n\'est pas une erreur de validation.');
        $this->assertNotAuthenticated();
    }

    public function testC11LaReponseNeReveleRienSurLIdentifiantDemande(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['userId' => 999999]);
        $first = $this->assertErrorResponse(401);

        $this->jsonRequest('POST', '/api/auth/login', ['userId' => 888888]);
        $second = $this->assertErrorResponse(401);

        // Même réponse quel que soit l'identifiant, sans écho de celui-ci.
        self::assertSame($first, $second);
        self::assertStringNotContainsString('999999', (string) json_encode($first));
        self::assertStringNotContainsString('888888', (string) json_encode($second));
    }

    public function testC12NouvelleConnexionRemplaceLaSession(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);

        $this->login($alice);
        self::assertResponseStatusCodeSame(200);
        $this->assertAuthenticatedAs($alice);

        $this->login($marc);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($marc->getId(), $this->responseJson()['id'] ?? null);

        $me = $this->assertAuthenticatedAs($marc);
        self::assertSame('manager', $me['role'] ?? null);
        self::assertEqualsCanonicalizing(['manager', 'employee'], $me['accessibleRoles'] ?? null);
    }

    public function testUneConnexionSansCorpsJsonEstRefuseeEn415(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);

        // Ce qu'enverrait un formulaire d'un autre site (CSRF de connexion)
        $this->client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['userId' => $alice->getId()], \JSON_THROW_ON_ERROR));

        $this->assertErrorResponse(415);
        $this->assertNotAuthenticated();
    }
}
