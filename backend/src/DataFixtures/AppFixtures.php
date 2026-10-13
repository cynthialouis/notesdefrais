<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Expense;
use App\Entity\ExpenseEvent;
use App\Entity\Receipt;
use App\Entity\User;
use App\Enum\ExpenseEventType;
use App\Enum\ExpenseStatus;
use App\Enum\UserRole;
use App\Service\ReceiptStorage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Comptes de démo : 1 admin, 2 managers rattachés à l'admin, 4 salariés (2 par manager), et des notes
 * dans chaque état avec leur historique, et des justificatifs PDF générés (spec 005).
 * Les catégories sont des données de référence insérées par migration : make db ne les purge pas.
 */
class AppFixtures extends Fixture
{
    public function __construct(private readonly ReceiptStorage $receipts)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->receipts->purge();

        $alice = $this->user($manager, 'Alice Martin', 'alice.martin@demo.test', UserRole::Admin);

        // Les managers ont l'admin pour responsable : c'est elle qui décide de leurs notes (spec 003, C13)
        $claire = $this->user($manager, 'Claire Dubois', 'claire.dubois@demo.test', UserRole::Manager, $alice);
        $hugo = $this->user($manager, 'Hugo Lefèvre', 'hugo.lefevre@demo.test', UserRole::Manager, $alice);

        $lea = $this->user($manager, 'Léa Bernard', 'lea.bernard@demo.test', UserRole::Employee, $claire);
        $tom = $this->user($manager, 'Tom Petit', 'tom.petit@demo.test', UserRole::Employee, $claire);
        $ines = $this->user($manager, 'Inès Moreau', 'ines.moreau@demo.test', UserRole::Employee, $hugo);
        $this->user($manager, 'Noah Laurent', 'noah.laurent@demo.test', UserRole::Employee, $hugo);

        // Politiques de départ (spec 004, C10) ; les catégories sont des données de référence déjà en base
        foreach (['repas' => [5000, false], 'hebergement' => [15000, true], 'transport' => [30000, false], 'materiel' => [50000, true], 'autre' => [null, false]] as $slug => [$max, $receipt]) {
            $this->category($manager, $slug)->setMaxAmountCents($max)->setReceiptRequired($receipt);
        }

        // Notes de Léa : un exemple de chaque état
        $this->expense($manager, $lea, 'Formation sécurité', 25000, 30, 'autre', [[ExpenseEventType::Submitted, $lea, 29], [ExpenseEventType::Approved, $claire, 27]])
            ->setReceipt($this->receipt('facture-formation-securite.pdf', 'Formation sécurité', '250,00 EUR'));
        $this->expense($manager, $lea, 'Taxi aéroport', 6200, 15, 'transport', [[ExpenseEventType::Submitted, $lea, 14], [ExpenseEventType::Rejected, $claire, 13, 'Précisez le trajet et joignez le reçu du taxi.']]);
        $this->expense($manager, $lea, 'Déjeuner client Dupont', 4250, 12, 'repas', [[ExpenseEventType::Submitted, $lea, 11]]);
        $this->expense($manager, $lea, 'Dîner client Martin', 6800, 6, 'repas', [[ExpenseEventType::Submitted, $lea, 5]]); // hors plafond (50 €)
        // Validée quand le plafond repas était de 80 € : reste conforme malgré le plafond actuel de 50 € (C9)
        $this->expense($manager, $lea, 'Repas d\'équipe fin de projet', 7200, 40, 'repas', [[ExpenseEventType::Submitted, $lea, 39], [ExpenseEventType::Approved, $claire, 38]])
            ->setLimitAtApprovalCents(8000);
        $this->expense($manager, $lea, 'Train Paris – Lyon', 8900, 5, 'transport');
        $this->expense($manager, $lea, 'Clavier de remplacement', 3999, 2, 'materiel');
        // Justificatif obligatoire et absent : la soumission est bloquée (spec 005, C10)
        $this->expense($manager, $lea, 'Hôtel formation Lyon', 11900, 4, 'hebergement');

        // À traiter par Claire (Tom), par Hugo (Inès), par Alice (Claire)
        $this->expense($manager, $tom, 'Hôtel salon Rennes', 12800, 20, 'hebergement', [[ExpenseEventType::Submitted, $tom, 19]])
            ->setReceipt($this->receipt('facture-hotel-rennes.pdf', 'Hôtel salon Rennes', '128,00 EUR'));
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
    private function expense(ObjectManager $manager, User $owner, string $label, int $amountCents, int $daysAgo, string $categorySlug, array $steps = []): Expense
    {
        $category = $this->category($manager, $categorySlug);

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
                ExpenseEventType::Approved => $expense->setStatus(ExpenseStatus::Approved)->setDecidedAt($at)->setDecidedBy($actor)->setLimitAtApprovalCents($category->getMaxAmountCents()),
                ExpenseEventType::Rejected => $expense->setStatus(ExpenseStatus::Rejected)->setDecidedAt($at)->setDecidedBy($actor)->setRejectionReason($step[3] ?? null),
                ExpenseEventType::Created => null,
            };
            $this->event($manager, $expense, $type, $actor, $stepDaysAgo, $step[3] ?? null);
        }

        return $expense;
    }

    /** Justificatif de démo : un PDF d'une page écrit dans le stockage, comme après un dépôt. */
    private function receipt(string $filename, string $title, string $amount): Receipt
    {
        $content = self::pdf(['Reçu', $title, 'Montant : '.$amount]);
        $path = (string) tempnam(sys_get_temp_dir(), 'demo-receipt-');
        file_put_contents($path, $content);

        return new Receipt($filename, 'application/pdf', \strlen($content), $this->receipts->store(new File($path)));
    }

    /**
     * PDF minimal d'une page (Helvetica, encodage WinAnsi pour les accents) : assez pour qu'un
     * navigateur l'affiche et que le type soit détecté d'après le contenu.
     *
     * @param list<string> $lines
     */
    private static function pdf(array $lines): string
    {
        $text = 'BT /F1 16 Tf 72 760 Td 22 TL';
        foreach ($lines as $line) {
            $encoded = (string) mb_convert_encoding($line, 'Windows-1252', 'UTF-8');
            $text .= ' ('.addcslashes($encoded, '()\\').') Tj T*';
        }
        $text .= ' ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.\strlen($text).' >>'."\nstream\n".$text."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = \strlen($pdf);
        $pdf .= 'xref'."\n0 ".(\count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= \sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(\count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function category(ObjectManager $manager, string $slug): Category
    {
        $category = $manager->getRepository(Category::class)->findOneBy(['slug' => $slug]);
        \assert($category instanceof Category, 'Catégorie absente : lancer les migrations avant les fixtures.');

        return $category;
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
