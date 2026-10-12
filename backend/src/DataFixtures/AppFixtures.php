<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\ExpenseEvent;
use App\Entity\User;
use App\Enum\ExpenseEventType;
use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Comptes de démo : 1 admin, 2 managers rattachés à l'admin, 4 salariés (2 par manager), et des notes
 * dans chaque état avec leur historique.
 * Les catégories sont des données de référence insérées par migration : make db ne les purge pas.
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $alice = $this->user($manager, 'Alice Martin', 'alice.martin@demo.test', UserRole::Admin);

        // Les managers ont l'admin pour responsable : c'est elle qui décide de leurs notes (spec 003, C13)
        $claire = $this->user($manager, 'Claire Dubois', 'claire.dubois@demo.test', UserRole::Manager, $alice);
        $hugo = $this->user($manager, 'Hugo Lefèvre', 'hugo.lefevre@demo.test', UserRole::Manager, $alice);

        $lea = $this->user($manager, 'Léa Bernard', 'lea.bernard@demo.test', UserRole::Employee, $claire);
        $tom = $this->user($manager, 'Tom Petit', 'tom.petit@demo.test', UserRole::Employee, $claire);
        $ines = $this->user($manager, 'Inès Moreau', 'ines.moreau@demo.test', UserRole::Employee, $hugo);
        $this->user($manager, 'Noah Laurent', 'noah.laurent@demo.test', UserRole::Employee, $hugo);

        // Notes de Léa : un exemple de chaque état
        $this->expense($manager, $lea, 'Formation sécurité', 25000, 30, 'autre', [[ExpenseEventType::Submitted, $lea, 29], [ExpenseEventType::Approved, $claire, 27]]);
        $this->expense($manager, $lea, 'Taxi aéroport', 6200, 15, 'transport', [[ExpenseEventType::Submitted, $lea, 14], [ExpenseEventType::Rejected, $claire, 13, 'Précisez le trajet et joignez le reçu du taxi.']]);
        $this->expense($manager, $lea, 'Déjeuner client Dupont', 4250, 12, 'repas', [[ExpenseEventType::Submitted, $lea, 11]]);
        $this->expense($manager, $lea, 'Train Paris – Lyon', 8900, 5, 'transport');
        $this->expense($manager, $lea, 'Clavier de remplacement', 3999, 2, 'materiel');

        // À traiter par Claire (Tom), par Hugo (Inès), par Alice (Claire)
        $this->expense($manager, $tom, 'Hôtel salon Rennes', 12800, 20, 'hebergement', [[ExpenseEventType::Submitted, $tom, 19]]);
        $this->expense($manager, $ines, 'Repas équipe projet', 9600, 8, 'repas', [[ExpenseEventType::Submitted, $ines, 7]]);
        $this->expense($manager, $claire, 'Train séminaire managers', 14500, 10, 'transport', [[ExpenseEventType::Submitted, $claire, 9]]);
        $this->expense($manager, $hugo, 'Casque audio visio', 7900, 3, 'materiel');

        $manager->flush();
    }

    /**
     * Crée une note et son historique. Chaque étape : [type, auteur, il y a N jours, motif éventuel].
     * L'état et les dates de la note découlent des étapes, comme si elles étaient passées par le Workflow.
     *
     * @param list<array{0: ExpenseEventType, 1: User, 2: int, 3?: string}> $steps
     */
    private function expense(ObjectManager $manager, User $owner, string $label, int $amountCents, int $daysAgo, string $categorySlug, array $steps = []): void
    {
        $category = $manager->getRepository(Category::class)->findOneBy(['slug' => $categorySlug]);
        \assert($category instanceof Category, 'Catégorie absente : lancer les migrations avant les fixtures.');

        $expense = (new Expense())
            ->setOwner($owner)
            ->setCategory($category)
            ->setLabel($label)
            ->setAmountCents($amountCents)
            ->setDate(new \DateTimeImmutable(\sprintf('-%d days midnight', $daysAgo), new \DateTimeZone('Europe/Paris')));
        $manager->persist($expense);
        $this->event($manager, $expense, ExpenseEventType::Created, $owner, $daysAgo);

        foreach ($steps as $step) {
            [$type, $actor, $stepDaysAgo] = $step;
            $at = new \DateTimeImmutable(\sprintf('-%d days 10:00', $stepDaysAgo), new \DateTimeZone('Europe/Paris'));
            match ($type) {
                ExpenseEventType::Submitted => $expense->setStatus(ExpenseStatus::Submitted)->setSubmittedAt($at),
                ExpenseEventType::Approved => $expense->setStatus(ExpenseStatus::Approved)->setDecidedAt($at)->setDecidedBy($actor),
                ExpenseEventType::Rejected => $expense->setStatus(ExpenseStatus::Rejected)->setDecidedAt($at)->setDecidedBy($actor)->setRejectionReason($step[3] ?? null),
                ExpenseEventType::Created => null,
            };
            $this->event($manager, $expense, $type, $actor, $stepDaysAgo, $step[3] ?? null);
        }
    }

    private function event(ObjectManager $manager, Expense $expense, ExpenseEventType $type, User $actor, int $daysAgo, ?string $comment = null): void
    {
        $event = new ExpenseEvent($expense, $actor, $type, $comment);
        // Date de l'événement dans le passé : réglée par réflexion, l'entité n'exposant pas de setter
        $property = new \ReflectionProperty(ExpenseEvent::class, 'createdAt');
        $property->setValue($event, new \DateTimeImmutable(\sprintf('-%d days 10:00', $daysAgo), new \DateTimeZone('Europe/Paris'))->setTimezone(new \DateTimeZone('UTC')));
        $manager->persist($event);
    }

    private function user(ObjectManager $manager, string $name, string $email, UserRole $role, ?User $teamManager = null): User
    {
        $user = (new User())
            ->setName($name)
            ->setEmail($email)
            ->setRole($role)
            ->setManager($teamManager);
        $manager->persist($user);

        return $user;
    }
}
