<?php

namespace App\Tests\Controller\Expense;

use App\Entity\ExpenseEvent;
use App\Enum\ExpenseEventType;
use App\Enum\ExpenseStatus;
use App\Repository\ExpenseEventRepository;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Historique d'une note : écriture (C10) et GET /api/expenses/{id}/events (C11) — spec 003.
 */
final class ExpenseEventsTest extends ApiTestCase
{
    public function testC10CreationSoumissionEtValidationSontInscritesAvecLeurAuteur(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);
        $before = time();
        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload());
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseJson()['id'];
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);
        $after = time();

        $this->client->request('GET', '/api/expenses/'.$id.'/events');

        self::assertResponseStatusCodeSame(200);
        $events = $this->responseJson();
        self::assertTrue(array_is_list($events));
        foreach ($events as $event) {
            $this->assertEventView($event);
            $this->assertIsoBetween($event['createdAt'], $before, $after);
        }
        self::assertSame(['created', 'submitted', 'approved'], array_column($events, 'type'));
        self::assertSame(
            [$team['sophie']->getId(), $team['sophie']->getId(), $team['marc']->getId()],
            array_map(static fn (array $e): mixed => $e['actor']['id'], $events),
        );
        self::assertSame(['Sophie Salariée', 'Sophie Salariée', 'Marc Manager'], array_map(static fn (array $e): mixed => $e['actor']['name'], $events));
        self::assertSame([null, null, null], array_column($events, 'comment'));

        $stored = $this->storedEvents($id);
        self::assertSame([ExpenseEventType::Created, ExpenseEventType::Submitted, ExpenseEventType::Approved], array_map(static fn (ExpenseEvent $e): ExpenseEventType => $e->getType(), $stored));
        self::assertSame($team['marc']->getId(), $stored[2]->getActor()->getId());
    }

    public function testC10LeRejetEstInscritAvecSonMotif(): void
    {
        $team = $this->createTeam();
        $id = $this->createdAndSubmittedBySophie($team);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => '  Justificatif illisible  ']);
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses/'.$id.'/events');

        self::assertResponseStatusCodeSame(200);
        $events = $this->responseJson();
        self::assertSame(['created', 'submitted', 'rejected'], array_column($events, 'type'));
        self::assertSame(['id' => $team['marc']->getId(), 'name' => 'Marc Manager'], $events[2]['actor']);
        self::assertSame('Justificatif illisible', $events[2]['comment']);

        $stored = $this->storedEvents($id);
        self::assertSame(ExpenseEventType::Rejected, $stored[2]->getType());
        self::assertSame('Justificatif illisible', $stored[2]->getComment());
    }

    public function testC10UnRefusNInscritRienDansLHistorique(): void
    {
        $team = $this->createTeam();
        $id = $this->createdAndSubmittedBySophie($team);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'abc']);
        self::assertResponseStatusCodeSame(400);
        $this->login($team['julie']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(404);

        self::assertSame(
            [ExpenseEventType::Created, ExpenseEventType::Submitted],
            array_map(static fn (ExpenseEvent $e): ExpenseEventType => $e->getType(), $this->storedEvents($id)),
        );
    }

    public function testC11DuPlusAncienAuPlusRecentAvecResoumission(): void
    {
        $team = $this->createTeam();
        $id = $this->createdAndSubmittedBySophie($team);
        $this->login($team['marc']);
        $this->jsonRequest('POST', '/api/expenses/'.$id.'/reject', ['reason' => 'Mauvaise catégorie']);
        self::assertResponseStatusCodeSame(200);
        $this->login($team['sophie']);
        $this->jsonRequest('PATCH', '/api/expenses/'.$id, $this->expensePayload(['categorySlug' => 'repas']));
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);
        $this->login($team['marc']);
        $this->client->request('POST', '/api/expenses/'.$id.'/approve');
        self::assertResponseStatusCodeSame(200);

        $this->client->request('GET', '/api/expenses/'.$id.'/events');

        self::assertResponseStatusCodeSame(200);
        $events = $this->responseJson();
        self::assertSame(['created', 'submitted', 'rejected', 'submitted', 'approved'], array_column($events, 'type'));
        self::assertSame([null, null, 'Mauvaise catégorie', null, null], array_column($events, 'comment'));
        $timestamps = array_map(static fn (array $e): int => (new \DateTimeImmutable($e['createdAt']))->getTimestamp(), $events);
        $sorted = $timestamps;
        sort($sorted);
        self::assertSame($sorted, $timestamps);
    }

    public function testC11LeProprietaireConsulteLHistoriqueDeSonBrouillon(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);
        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload());
        $id = $this->responseJson()['id'];

        $this->client->request('GET', '/api/expenses/'.$id.'/events');

        self::assertResponseStatusCodeSame(200);
        $events = $this->responseJson();
        self::assertCount(1, $events);
        $this->assertEventView($events[0]);
        self::assertSame('created', $events[0]['type']);
        self::assertSame(['id' => $team['sophie']->getId(), 'name' => 'Sophie Salariée'], $events[0]['actor']);
    }

    public function testC11LeManagerConsulteLHistoriqueDUneNoteSoumiseDeSonEquipe(): void
    {
        $team = $this->createTeam();
        $id = $this->createdAndSubmittedBySophie($team);
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$id.'/events');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['created', 'submitted'], array_column($this->responseJson(), 'type'));
    }

    public function testAutorisationLeManagerNeVoitPasLHistoriqueDUnBrouillonDeSonEquipe(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);
        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => 'Brouillon de Sophie']));
        $id = $this->responseJson()['id'];
        $this->login($team['marc']);

        $this->client->request('GET', '/api/expenses/'.$id.'/events');

        $this->assertErrorResponse(404);
        self::assertStringNotContainsString('Sophie', (string) $this->client->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function strangers(): iterable
    {
        yield 'manager d\'une autre équipe' => ['julie'];
        yield 'collègue salarié' => ['tom'];
        yield 'admin, responsable du manager mais pas du salarié' => ['admin'];
    }

    #[DataProvider('strangers')]
    public function testAutorisationToutAutreRecoit404(string $reader): void
    {
        $team = $this->createTeam();
        $id = $this->createdAndSubmittedBySophie($team);
        $this->login($team[$reader]);

        $this->client->request('GET', '/api/expenses/'.$id.'/events');

        $error = $this->assertErrorResponse(404);
        self::assertArrayNotHasKey('fields', $error);
        self::assertStringNotContainsString('Sophie', (string) $this->client->getResponse()->getContent());
    }

    public function testAutorisationNoteInexistanteRenvoie404(): void
    {
        $team = $this->createTeam();
        $id = $this->createdAndSubmittedBySophie($team);

        $this->client->request('GET', '/api/expenses/'.($id + 1000).'/events');

        $this->assertErrorResponse(404);
    }

    public function testAutorisationSansSessionRenvoie401(): void
    {
        $team = $this->createTeam();
        $id = $this->createdAndSubmittedBySophie($team);
        $this->client->request('POST', '/api/auth/logout');

        $this->client->request('GET', '/api/expenses/'.$id.'/events');

        $this->assertErrorResponse(401);
        self::assertStringNotContainsString('Sophie', (string) $this->client->getResponse()->getContent());
    }

    public function testValidationIdentifiantNonNumeriqueRenvoie404(): void
    {
        $team = $this->createTeam();
        $this->login($team['sophie']);

        $this->client->request('GET', '/api/expenses/abc/events');

        $this->assertErrorResponse(404);
    }

    /**
     * Crée la note par l'API (événement « created ») puis la soumet ; Sophie reste connectée.
     *
     * @param array<string, \App\Entity\User> $team
     */
    private function createdAndSubmittedBySophie(array $team): int
    {
        $this->login($team['sophie']);
        $this->jsonRequest('POST', '/api/expenses', $this->expensePayload(['label' => 'Note de Sophie']));
        self::assertResponseStatusCodeSame(201);
        $id = $this->responseJson()['id'];
        self::assertIsInt($id);
        $this->client->request('POST', '/api/expenses/'.$id.'/submit');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(ExpenseStatus::Submitted, $this->findExpense($id)?->getStatus());

        return $id;
    }

    /**
     * @return list<ExpenseEvent>
     */
    private function storedEvents(int $expenseId): array
    {
        $this->entityManager()->clear();
        $repository = static::getContainer()->get(ExpenseEventRepository::class);

        return array_values($repository->findBy(['expense' => $expenseId], ['createdAt' => 'ASC', 'id' => 'ASC']));
    }

    /**
     * @param array<mixed> $event
     */
    private function assertEventView(array $event): void
    {
        self::assertEqualsCanonicalizing(['type', 'actor', 'comment', 'createdAt'], array_keys($event));
        self::assertContains($event['type'], ['created', 'submitted', 'approved', 'rejected']);
        $this->assertPersonRef($event['actor']);
        if (null !== $event['comment']) {
            self::assertIsString($event['comment']);
        }
        self::assertIsString($event['createdAt']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $event['createdAt']);
    }
}
