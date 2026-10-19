<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\LeaveRequest;
use App\Entity\User;
use App\Enum\LeaveStatus;
use App\Tests\Support\ApiTestCase;

/**
 * Données de démo des congés — spec 007 (data-model.md, section « Données de démo ») : pour chaque manager, au moins
 * une demande en attente de son équipe ; des congés acceptés à venir dans les deux équipes ; une demande refusée,
 * une retirée, une annulée ; une demande en attente d'un manager, à traiter par l'admin ; un congé accepté terminé
 * hier, absent de la liste des congés de tous.
 *
 * Les demandes sont datées en relatif depuis la date du jour : le temps n'est pas figé ici, « aujourd'hui » est
 * calculé depuis l'heure réelle, en heure de Paris.
 */
final class LeaveFixturesTest extends ApiTestCase
{
    /**
     * @return array<string, User> comptes de démo par adresse e-mail
     */
    private function loadDemo(): array
    {
        $fixtures = static::getContainer()->get(AppFixtures::class);
        self::assertInstanceOf(AppFixtures::class, $fixtures);
        $em = $this->entityManager();
        $fixtures->load($em);
        $em->clear();

        $users = [];
        foreach ($em->getRepository(User::class)->findAll() as $user) {
            $users[$user->getEmail()] = $user;
        }

        return $users;
    }

    /**
     * @return list<LeaveRequest>
     */
    private function leaves(?LeaveStatus $status = null): array
    {
        $repository = $this->entityManager()->getRepository(LeaveRequest::class);

        return array_values(null === $status ? $repository->findAll() : $repository->findBy(['status' => $status]));
    }

    private function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
    }

    private function yesterday(): string
    {
        return (new \DateTimeImmutable('yesterday', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
    }

    /**
     * @param array<string, User> $users
     *
     * @return list<int> identifiants des subordonnés directs
     */
    private function teamIds(array $users, string $managerEmail): array
    {
        $manager = $users[$managerEmail];
        $ids = [];
        foreach ($users as $user) {
            if ($user->getManager()?->getId() === $manager->getId()) {
                $ids[] = (int) $user->getId();
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function requesterIds(mixed $list): array
    {
        self::assertIsArray($list);

        return array_map(static fn (array $view): int => $view['requester']['id'], $list);
    }

    public function testC6ChaqueManagerAAuMoinsUneDemandeEnAttenteDeSonEquipe(): void
    {
        $users = $this->loadDemo();
        foreach (['claire.dubois@demo.test', 'hugo.lefevre@demo.test'] as $email) {
            $this->login($users[$email]);
            $this->client->request('GET', '/api/team/leaves');

            self::assertResponseStatusCodeSame(200);
            $requesters = $this->requesterIds($this->responseJson());
            self::assertNotEmpty($requesters, $email.' doit avoir au moins une demande à traiter.');
            self::assertSame([], array_diff($requesters, $this->teamIds($users, $email)));
        }
    }

    public function testC15DesCongesAcceptesAVenirDansLesDeuxEquipes(): void
    {
        $users = $this->loadDemo();
        $this->login($users['noah.laurent@demo.test']);

        $this->client->request('GET', '/api/leaves');

        self::assertResponseStatusCodeSame(200);
        $requesters = $this->requesterIds($this->responseJson());
        foreach (['claire.dubois@demo.test', 'hugo.lefevre@demo.test'] as $email) {
            self::assertNotEmpty(array_intersect($requesters, $this->teamIds($users, $email)), 'Congé accepté à venir attendu dans l\'équipe de '.$email.'.');
        }
    }

    public function testC15UnCongeAccepteTermineHierEstAbsentDeLaListeDesConges(): void
    {
        $users = $this->loadDemo();
        $yesterday = $this->yesterday();
        $endedYesterday = array_values(array_filter(
            $this->leaves(LeaveStatus::Accepted),
            static fn (LeaveRequest $leave): bool => $leave->getEndDate()->format('Y-m-d') === $yesterday,
        ));
        self::assertNotEmpty($endedYesterday, 'La démo doit contenir un congé accepté terminé hier.');
        $id = $endedYesterday[0]->getId();
        $this->login($users['lea.bernard@demo.test']);

        $this->client->request('GET', '/api/leaves');

        self::assertResponseStatusCodeSame(200);
        $list = $this->responseJson();
        self::assertNotContains($id, array_column($list, 'id'));
        foreach ($list as $view) {
            self::assertGreaterThanOrEqual($this->today(), $view['endDate']);
        }
    }

    public function testC8C11C13UneDemandeRefuseeUneRetireeEtUneAnnuleeAvecLeursMotifs(): void
    {
        $users = $this->loadDemo();
        $refused = $this->leaves(LeaveStatus::Refused);
        self::assertNotEmpty($refused, 'Une demande refusée attendue.');
        foreach ($refused as $leave) {
            self::assertNotEmpty($leave->getRefusalReason());
            self::assertSame($leave->getRequester()->getManager()?->getId(), $leave->getDecidedBy()?->getId(), 'Refusée par le responsable du demandeur.');
            self::assertNotNull($leave->getDecidedAt());
        }
        self::assertNotEmpty($this->leaves(LeaveStatus::Withdrawn), 'Une demande retirée attendue.');
        $cancelled = $this->leaves(LeaveStatus::Cancelled);
        self::assertNotEmpty($cancelled, 'Une demande annulée attendue.');
        foreach ($cancelled as $leave) {
            self::assertNotEmpty($leave->getCancellationReason());
            self::assertSame($users['alice.martin@demo.test']->getId(), $leave->getCancelledBy()?->getId(), 'Annulée par l\'admin.');
            self::assertNotNull($leave->getCancelledAt());
        }
    }

    public function testC18LAdminAUneDemandeDeManagerATraiterParmiLesDemandesEnAttente(): void
    {
        $users = $this->loadDemo();
        $managerIds = [(int) $users['claire.dubois@demo.test']->getId(), (int) $users['hugo.lefevre@demo.test']->getId()];
        $this->login($users['alice.martin@demo.test']);

        $this->client->request('GET', '/api/leaves/pending');
        self::assertResponseStatusCodeSame(200);
        $pending = $this->responseJson();
        self::assertNotEmpty(array_intersect($this->requesterIds($pending), $managerIds), 'Une demande en attente d\'un manager attendue.');
        self::assertGreaterThan(\count(array_intersect($this->requesterIds($pending), $managerIds)), \count($pending), 'Des demandes en attente de salariés attendues aussi.');

        $this->client->request('GET', '/api/team/leaves');
        self::assertResponseStatusCodeSame(200);
        self::assertNotEmpty($this->responseJson(), 'L\'admin doit avoir une demande de manager à décider.');
    }

    public function testC4C5LaDemoEstCoherenteAvecLesRegles(): void
    {
        $users = $this->loadDemo();
        $leaves = $this->leaves();
        foreach ($leaves as $leave) {
            // C5 : l'admin n'a pas de responsable, il n'a aucune demande
            self::assertNotSame($users['alice.martin@demo.test']->getId(), $leave->getRequester()->getId());
            self::assertLessThanOrEqual($leave->getEndDate()->format('Y-m-d'), $leave->getStartDate()->format('Y-m-d'));
            if (LeaveStatus::Accepted === $leave->getStatus()) {
                self::assertSame($leave->getRequester()->getManager()?->getId(), $leave->getDecidedBy()?->getId(), 'Acceptée par le responsable du demandeur.');
            }
            if (LeaveStatus::Pending === $leave->getStatus()) {
                self::assertGreaterThanOrEqual($this->today(), $leave->getStartDate()->format('Y-m-d'), 'Une demande en attente ne commence pas dans le passé.');
            }
        }

        // C4 : aucune paire de demandes en attente ou acceptées d'une même personne ne se chevauche
        $blocking = array_values(array_filter($leaves, static fn (LeaveRequest $leave): bool => \in_array($leave->getStatus(), [LeaveStatus::Pending, LeaveStatus::Accepted], true)));
        foreach ($blocking as $i => $a) {
            foreach (\array_slice($blocking, $i + 1) as $b) {
                if ($a->getRequester()->getId() !== $b->getRequester()->getId()) {
                    continue;
                }
                $overlap = $a->getStartDate() <= $b->getEndDate() && $a->getEndDate() >= $b->getStartDate();
                self::assertFalse($overlap, 'Deux demandes de '.$a->getRequester()->getName().' se chevauchent.');
            }
        }
    }
}
