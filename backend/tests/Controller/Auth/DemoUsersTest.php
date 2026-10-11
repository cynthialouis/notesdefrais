<?php

namespace App\Tests\Controller\Auth;

use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;

/**
 * GET /api/auth/demo-users (public).
 */
final class DemoUsersTest extends ApiTestCase
{
    public function testC1ListeLesComptesDeDemoAvecNomEtRole(): void
    {
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $manager = $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager);
        $employee = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee, $manager);

        $this->client->request('GET', '/api/auth/demo-users');

        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertTrue(array_is_list($list), 'La réponse doit être une liste JSON.');

        $byId = [];
        foreach ($list as $item) {
            self::assertIsArray($item);
            self::assertEqualsCanonicalizing(['id', 'name', 'role'], array_keys($item), 'Chaque compte expose seulement id, name et role.');
            $byId[$item['id']] = $item;
        }

        self::assertSame(['id' => $admin->getId(), 'name' => 'Alice Admin', 'role' => 'admin'], $this->ordered($byId[$admin->getId()] ?? []));
        self::assertSame(['id' => $manager->getId(), 'name' => 'Marc Manager', 'role' => 'manager'], $this->ordered($byId[$manager->getId()] ?? []));
        self::assertSame(['id' => $employee->getId(), 'name' => 'Sophie Salariée', 'role' => 'employee'], $this->ordered($byId[$employee->getId()] ?? []));
    }

    public function testC1ListeAccessibleSansEtreConnecte(): void
    {
        $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);

        $this->client->request('GET', '/api/auth/demo-users');

        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $this->responseJson());
        // Consulter la liste n'ouvre aucune session.
        $this->assertNotAuthenticated();
    }

    public function testC1ListeVideSansCompte(): void
    {
        $this->client->request('GET', '/api/auth/demo-users');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->responseJson());
    }

    /**
     * @param array<mixed> $item
     *
     * @return array<mixed>
     */
    private function ordered(array $item): array
    {
        return ['id' => $item['id'] ?? null, 'name' => $item['name'] ?? null, 'role' => $item['role'] ?? null];
    }
}
