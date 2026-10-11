<?php

namespace App\Tests\Support;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests de routes de l'API.
 *
 * La base de test est migrée par `make test` et remise à zéro entre les tests
 * par dama/doctrine-test-bundle (rollback de transaction) : chaque test crée
 * lui-même les utilisateurs dont il a besoin.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    /**
     * Crée et enregistre un utilisateur (entité prévue par la spec 001 : name, email, role, manager).
     */
    protected function createUser(string $name, string $email, UserRole $role, ?User $manager = null): User
    {
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setRole($role);
        $user->setManager($manager);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Envoie une requête JSON. `$body` est encodé en JSON s'il n'est pas déjà une chaîne.
     */
    protected function jsonRequest(string $method, string $uri, mixed $body = null): void
    {
        $content = null;
        if (null !== $body) {
            $content = \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR);
        }

        $this->client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $content);
    }

    protected function login(User $user): void
    {
        $this->jsonRequest('POST', '/api/auth/login', ['userId' => $user->getId()]);
    }

    /**
     * @return array<mixed>
     */
    protected function responseJson(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        self::assertJson($content, 'La réponse doit être du JSON : '.$content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * Vérifie le format d'erreur unique `{ error: { code, message, fields? } }` et le renvoie.
     *
     * @return array<mixed>
     */
    protected function assertErrorResponse(int $status): array
    {
        self::assertResponseStatusCodeSame($status);
        $data = $this->responseJson();

        self::assertArrayHasKey('error', $data);
        self::assertIsArray($data['error']);
        self::assertArrayHasKey('code', $data['error']);
        self::assertIsString($data['error']['code']);
        self::assertNotSame('', $data['error']['code']);
        self::assertArrayHasKey('message', $data['error']);
        self::assertIsString($data['error']['message']);
        self::assertNotSame('', $data['error']['message']);

        return $data['error'];
    }

    /**
     * Vérifie qu'aucune session n'est ouverte : GET /api/me répond 401.
     */
    protected function assertNotAuthenticated(): void
    {
        $this->client->request('GET', '/api/me');
        $this->assertErrorResponse(401);
    }

    /**
     * Vérifie que GET /api/me renvoie l'utilisateur attendu.
     *
     * @return array<mixed>
     */
    protected function assertAuthenticatedAs(User $user): array
    {
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(200);
        $me = $this->responseJson();
        self::assertSame($user->getId(), $me['id'] ?? null);
        self::assertSame($user->getEmail(), $me['email'] ?? null);

        return $me;
    }
}
