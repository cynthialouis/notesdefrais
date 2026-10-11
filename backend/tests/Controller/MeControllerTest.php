<?php

namespace App\Tests\Controller;

use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * GET /api/me (connecté).
 */
final class MeControllerTest extends ApiTestCase
{
    public function testC3UtilisateurReconnuSurUneNouvelleRequete(): void
    {
        $manager = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $employee = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $manager);
        $this->login($employee);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(200);
        $me = $this->responseJson();
        self::assertEqualsCanonicalizing(['id', 'name', 'email', 'role', 'accessibleRoles'], array_keys($me));
        self::assertSame($employee->getId(), $me['id']);
        self::assertSame('Sophie Salariée', $me['name']);
        self::assertSame('sophie@demo.test', $me['email']);
        self::assertSame('employee', $me['role']);
        self::assertSame(['employee'], $me['accessibleRoles']);
    }

    public function testC3UtilisateurReconnuSurPlusieursRequetesSuccessives(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($alice);

        $this->assertAuthenticatedAs($alice);
        $this->assertAuthenticatedAs($alice);
    }

    public function testC5MeSansSessionRenvoie401AuFormatDErreur(): void
    {
        $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);

        $this->client->request('GET', '/api/me');

        $error = $this->assertErrorResponse(401);
        self::assertArrayNotHasKey('id', $this->responseJson());
        self::assertArrayNotHasKey('fields', $error);
    }

    public function testC5MeAvecCookieAltereRenvoie401(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($alice);
        self::assertResponseStatusCodeSame(200);

        $jar = $this->client->getCookieJar();
        $cookies = $jar->all();
        self::assertNotEmpty($cookies, 'La connexion doit poser un cookie de session.');
        foreach ($cookies as $cookie) {
            $jar->set(new Cookie($cookie->getName(), 'cookie-altere-'.bin2hex(random_bytes(4)), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain()));
        }

        $this->assertNotAuthenticated();
    }

    public function testC5MeSansCookieDeSessionRenvoie401MemeApresUneConnexionAilleurs(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($alice);
        self::assertResponseStatusCodeSame(200);

        // Un autre navigateur (sans cookie) n'est pas connecté.
        $this->client->getCookieJar()->clear();

        $this->assertNotAuthenticated();
    }

    public function testC9AdminAccedeAuxRolesAdminManagerEtSalarie(): void
    {
        $alice = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($alice);

        $me = $this->assertAuthenticatedAs($alice);
        self::assertSame('admin', $me['role']);
        self::assertEqualsCanonicalizing(['admin', 'manager', 'employee'], $me['accessibleRoles']);
    }

    public function testC9ManagerAccedeAuxRolesManagerEtSalarie(): void
    {
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $this->login($marc);

        $me = $this->assertAuthenticatedAs($marc);
        self::assertSame('manager', $me['role']);
        self::assertEqualsCanonicalizing(['manager', 'employee'], $me['accessibleRoles']);
    }

    public function testC9SalarieAccedeAuSeulRoleSalarie(): void
    {
        $marc = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $marc);
        $this->login($sophie);

        $me = $this->assertAuthenticatedAs($sophie);
        self::assertSame('employee', $me['role']);
        self::assertSame(['employee'], $me['accessibleRoles']);
    }

    public function testC9LeRoleVientDeLaBaseEtNonDuClient(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);

        // Un client qui prétend être admin dans sa requête de connexion n'obtient rien de plus.
        $this->jsonRequest('POST', '/api/auth/login', ['userId' => $sophie->getId(), 'role' => 'admin']);
        $status = $this->client->getResponse()->getStatusCode();
        self::assertContains($status, [200, 400], 'Un champ en trop est soit ignoré, soit refusé.');

        if (200 === $status) {
            $me = $this->assertAuthenticatedAs($sophie);
            self::assertSame('employee', $me['role']);
            self::assertSame(['employee'], $me['accessibleRoles']);
        } else {
            $this->assertNotAuthenticated();
        }
    }
}
