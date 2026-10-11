<?php

namespace App\Service;

use App\Dto\ExpenseInput;
use App\Entity\Expense;
use App\Entity\User;
use App\Http\PublicHttpException;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Règles d'écriture d'une note. Le statut ne change que par le Workflow « expense » ;
 * ce qui est modifiable ou supprimable est décidé par ExpenseStatus::isEditable().
 * Les entrées sont déjà validées (ExpenseInput) et le droit sur la note déjà vérifié (ExpenseVoter).
 */
final class ExpenseManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categories,
        #[Target('expense')]
        private readonly WorkflowInterface $workflow,
    ) {
    }

    public function create(User $owner, ExpenseInput $input): Expense
    {
        $expense = (new Expense())->setOwner($owner);
        $this->apply($expense, $input);

        $this->em->persist($expense);
        $this->em->flush();

        return $expense;
    }

    public function update(Expense $expense, ExpenseInput $input): Expense
    {
        $this->assertEditable($expense);
        $this->apply($expense, $input);
        $this->em->flush();

        return $expense;
    }

    public function submit(Expense $expense): Expense
    {
        if (!$this->workflow->can($expense, 'submit')) {
            throw PublicHttpException::conflict('Seul un brouillon peut être soumis.');
        }
        $this->workflow->apply($expense, 'submit');
        $this->em->flush();

        return $expense;
    }

    public function delete(Expense $expense): void
    {
        $this->assertEditable($expense);
        $this->em->remove($expense);
        $this->em->flush();
    }

    private function assertEditable(Expense $expense): void
    {
        if (!$expense->getStatus()->isEditable()) {
            throw PublicHttpException::conflict('Cette note n\'est plus en brouillon : elle ne peut plus être modifiée ni supprimée.');
        }
    }

    private function apply(Expense $expense, ExpenseInput $input): void
    {
        $category = $this->categories->findOneBy(['slug' => $input->categorySlug]);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $input->date);
        \assert(null !== $category && false !== $date && null !== $input->label && \is_int($input->amountCents), 'ExpenseInput doit être validé avant.');

        $expense
            ->setLabel(trim($input->label))
            ->setAmountCents($input->amountCents)
            ->setDate($date)
            ->setCategory($category);
    }
}
