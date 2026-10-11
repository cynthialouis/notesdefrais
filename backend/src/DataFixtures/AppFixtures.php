<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Comptes de démo : 1 admin, 2 managers, 4 salariés (2 par manager), et quelques notes.
 * Les catégories sont des données de référence insérées par migration : make db ne les purge pas.
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->user($manager, 'Alice Martin', 'alice.martin@demo.test', UserRole::Admin);

        $claire = $this->user($manager, 'Claire Dubois', 'claire.dubois@demo.test', UserRole::Manager);
        $hugo = $this->user($manager, 'Hugo Lefèvre', 'hugo.lefevre@demo.test', UserRole::Manager);

        $lea = $this->user($manager, 'Léa Bernard', 'lea.bernard@demo.test', UserRole::Employee, $claire);
        $tom = $this->user($manager, 'Tom Petit', 'tom.petit@demo.test', UserRole::Employee, $claire);
        $this->user($manager, 'Inès Moreau', 'ines.moreau@demo.test', UserRole::Employee, $hugo);
        $this->user($manager, 'Noah Laurent', 'noah.laurent@demo.test', UserRole::Employee, $hugo);

        $this->expense($manager, $lea, 'Déjeuner client Dupont', 4250, '-12 days', 'repas', ExpenseStatus::Submitted);
        $this->expense($manager, $lea, 'Train Paris – Lyon', 8900, '-5 days', 'transport', ExpenseStatus::Draft);
        $this->expense($manager, $lea, 'Clavier de remplacement', 3999, '-2 days', 'materiel', ExpenseStatus::Draft);
        $this->expense($manager, $tom, 'Hôtel salon Rennes', 12800, '-20 days', 'hebergement', ExpenseStatus::Submitted);

        $manager->flush();
    }

    private function expense(ObjectManager $manager, User $owner, string $label, int $amountCents, string $daysAgo, string $categorySlug, ExpenseStatus $status): void
    {
        $category = $manager->getRepository(Category::class)->findOneBy(['slug' => $categorySlug]);
        \assert($category instanceof Category, 'Catégorie absente : lancer les migrations avant les fixtures.');

        $date = new \DateTimeImmutable($daysAgo, new \DateTimeZone('Europe/Paris'));
        $expense = (new Expense())
            ->setOwner($owner)
            ->setCategory($category)
            ->setLabel($label)
            ->setAmountCents($amountCents)
            ->setDate($date->setTime(0, 0))
            ->setStatus($status)
            ->setSubmittedAt(ExpenseStatus::Submitted === $status ? $date->modify('+1 day') : null);
        $manager->persist($expense);
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
