<?php

namespace App\Tests\Controller;

use App\Enum\UserRole;
use App\Tests\Support\ApiTestCase;

/**
 * GET /api/categories (connecté) — spec 002, politique associée depuis la spec 004 (C1, C2).
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
            // Spec 004 : la vue d'une catégorie porte sa politique.
            self::assertEqualsCanonicalizing(['slug', 'label', 'maxAmountCents', 'receiptRequired'], array_keys($category));
        }
        // Base de test : catégories insérées par la migration, sans politique.
        self::assertEqualsCanonicalizing([
            ['slug' => 'repas', 'label' => 'Repas', 'maxAmountCents' => null, 'receiptRequired' => false],
            ['slug' => 'transport', 'label' => 'Transport', 'maxAmountCents' => null, 'receiptRequired' => false],
            ['slug' => 'hebergement', 'label' => 'Hébergement', 'maxAmountCents' => null, 'receiptRequired' => false],
            ['slug' => 'materiel', 'label' => 'Matériel', 'maxAmountCents' => null, 'receiptRequired' => false],
            ['slug' => 'autre', 'label' => 'Autre', 'maxAmountCents' => null, 'receiptRequired' => false],
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

    public function testC1ChaqueCategorieExposeSonPlafondEtSonJustificatifObligatoire(): void
    {
        $this->setPolicy('repas', 5000);
        $this->setPolicy('hebergement', 15000, true);
        $this->setPolicy('autre', null, true);
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->client->request('GET', '/api/categories');

        self::assertResponseStatusCodeSame(200);
        $bySlug = array_column($this->responseJson(), null, 'slug');
        self::assertSame(['slug' => 'repas', 'label' => 'Repas', 'maxAmountCents' => 5000, 'receiptRequired' => false], $bySlug['repas'] ?? null);
        self::assertSame(['slug' => 'hebergement', 'label' => 'Hébergement', 'maxAmountCents' => 15000, 'receiptRequired' => true], $bySlug['hebergement'] ?? null);
        // Aucun plafond : null.
        self::assertSame(['slug' => 'autre', 'label' => 'Autre', 'maxAmountCents' => null, 'receiptRequired' => true], $bySlug['autre'] ?? null);
        self::assertSame(['slug' => 'transport', 'label' => 'Transport', 'maxAmountCents' => null, 'receiptRequired' => false], $bySlug['transport'] ?? null);
    }

    public function testC1LaPolitiqueEstLisibleParTousLesConnectes(): void
    {
        $this->setPolicy('materiel', 50000, true);
        foreach ([
            $this->createUser('Sophie Salariée', 'sophie@demo.test', UserRole::Employee),
            $this->createUser('Marc Manager', 'marc@demo.test', UserRole::Manager),
        ] as $user) {
            $this->login($user);
            $this->client->request('GET', '/api/categories');
            self::assertResponseStatusCodeSame(200);
            $bySlug = array_column($this->responseJson(), null, 'slug');
            self::assertSame(50000, $bySlug['materiel']['maxAmountCents'] ?? 'absent');
            self::assertTrue($bySlug['materiel']['receiptRequired'] ?? false);
        }
    }

    public function testC2LaPolitiqueModifieeParLAdminEstRenvoyeeParLaListe(): void
    {
        $admin = $this->createUser('Alice Admin', 'alice@demo.test', UserRole::Admin);
        $this->login($admin);

        $this->jsonRequest('PUT', '/api/policies/transport', ['maxAmountCents' => 30000, 'receiptRequired' => true]);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/api/categories');

        self::assertResponseStatusCodeSame(200);
        $bySlug = array_column($this->responseJson(), null, 'slug');
        self::assertSame(['slug' => 'transport', 'label' => 'Transport', 'maxAmountCents' => 30000, 'receiptRequired' => true], $bySlug['transport'] ?? null);
    }
}
