<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Category;
use App\Entity\Expense;
use App\Service\PolicyChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Politiques de départ des données de démo — spec 004 (C10), et notes hors plafond (plan).
 */
final class PolicyFixturesTest extends KernelTestCase
{
    public function testC10LesDonneesDeDemoPortentLesPolitiquesDeDepart(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        static::getContainer()->get(AppFixtures::class)->load($em); // service : ses dépendances sont injectées (spec 005, justificatifs de démo)
        $em->clear();

        $expected = [
            'repas' => [5000, false],
            'hebergement' => [15000, true],
            'transport' => [30000, false],
            'materiel' => [50000, true],
            'autre' => [null, false],
        ];
        foreach ($expected as $slug => [$max, $receipt]) {
            $category = $em->getRepository(Category::class)->findOneBy(['slug' => $slug]);
            self::assertInstanceOf(Category::class, $category, "Catégorie '$slug' absente.");
            self::assertSame($max, $category->getMaxAmountCents(), "Plafond de '$slug'.");
            self::assertSame($receipt, $category->isReceiptRequired(), "Justificatif obligatoire de '$slug'.");
        }
    }

    public function testC10LesDonneesDeDemoContiennentDesNotesHorsPlafond(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        static::getContainer()->get(AppFixtures::class)->load($em); // service : ses dépendances sont injectées (spec 005, justificatifs de démo)
        $em->clear();

        $checker = static::getContainer()->get(PolicyChecker::class);
        self::assertInstanceOf(PolicyChecker::class, $checker);
        $over = array_filter(
            $em->getRepository(Expense::class)->findAll(),
            static fn (Expense $expense): bool => null !== $checker->check($expense)->overLimitByCents,
        );
        self::assertNotEmpty($over, 'La démo doit contenir au moins une note hors plafond (plan de la spec 004).');
    }
}
