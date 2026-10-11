<?php

namespace App\Tests\Controller\Auth;

use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;

/**
 * POST /api/auth/logout (connecté).
 */
final class LogoutTest extends ApiTestCase
{
    public function testC4DeconnexionRenvoie204EtFermeLaSession(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($alice);
        $this->assertAuthenticatedAs($alice);

        $this->client->request('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $this->client->getResponse()->getContent());
        $this->assertNotAuthenticated();
    }

    public function testC4ApresDeconnexionUneSecondeDeconnexionRenvoie401(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($alice);
        $this->client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('POST', '/api/auth/logout');

        $this->assertErrorResponse(401);
    }

    public function testC4ApresDeconnexionOnPeutSeReconnecter(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $this->login($alice);
        $this->client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $this->login($marc);

        self::assertResponseStatusCodeSame(200);
        $this->assertAuthenticatedAs($marc);
    }

    public function testC5DeconnexionSansSessionRenvoie401AuFormatDErreur(): void
    {
        $this->client->request('POST', '/api/auth/logout');

        $error = $this->assertErrorResponse(401);
        self::assertArrayNotHasKey('fields', $error);
    }

    public function testC12DeconnexionApresChangementDeCompteNeLaissePasLAncienneSession(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $this->login($alice);
        $this->login($marc);

        $this->client->request('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
        $this->assertNotAuthenticated();
    }

    public function testLaDeconnexionParGetEstRefuseeEtLaSessionConservee(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($alice);

        // Un lien ou une image pointant vers la déconnexion ne doit pas fermer la session
        $this->client->request('GET', '/api/auth/logout');

        $this->assertErrorResponse(405);
        $this->assertAuthenticatedAs($alice);
    }
}
