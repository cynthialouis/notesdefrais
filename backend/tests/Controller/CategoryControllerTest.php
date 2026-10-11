<?php

namespace App\Tests\Controller;

use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;

/**
 * GET /api/categories (connecté) — spec 002.
 */
final class CategoryControllerTest extends ApiTestCase
{
    public function testC10ListeLesCinqCategoriesAvecLeurLibelle(): void
    {
        $sophie = $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee);
        $this->login($sophie);

        $this->client->request('GET', '/api/categories');

        self::assertResponseStatusCodeSame(200);
        $categories = $this->responseJson();
        self::assertTrue(array_is_list($categories));
        foreach ($categories as $category) {
            self::assertIsArray($category);
            self::assertEqualsCanonicalizing(['slug', 'label'], array_keys($category));
        }
        self::assertEqualsCanonicalizing([
            ['slug' => 'repas', 'label' => 'Repas'],
            ['slug' => 'transport', 'label' => 'Transport'],
            ['slug' => 'hebergement', 'label' => 'Hébergement'],
            ['slug' => 'materiel', 'label' => 'Matériel'],
            ['slug' => 'autre', 'label' => 'Autre'],
        ], $categories);
    }

    public function testC10LesCategoriesSontEnBase(): void
    {
        foreach (['repas' => 'Repas', 'transport' => 'Transport', 'hebergement' => 'Hébergement', 'materiel' => 'Matériel', 'autre' => 'Autre'] as $slug => $label) {
            self::assertSame($label, $this->category($slug)->getLabel());
        }
    }

    public function testC10CategoriesAccessiblesAuManagerEtALAdmin(): void
    {
        foreach ([
            $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager),
            $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin),
        ] as $user) {
            $this->login($user);
            $this->client->request('GET', '/api/categories');
            self::assertResponseStatusCodeSame(200);
            self::assertCount(5, $this->responseJson());
        }
    }

    public function testC10CategoriesSansSessionRenvoie401(): void
    {
        $this->client->request('GET', '/api/categories');

        $error = $this->assertErrorResponse(401);
        self::assertArrayNotHasKey('fields', $error);
        self::assertArrayNotHasKey(0, $this->responseJson());
    }
}
