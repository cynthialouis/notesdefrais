<?php

namespace App\Service;

use App\Dto\ExpenseInput;
use App\Entity\Expense;
use App\Entity\ExpenseEvent;
use App\Entity\User;
use App\Enum\ExpenseEventType;
use App\Http\PublicHttpException;
use App\Repository\CategoryRepository;
use App\Text\UnicodeTrim;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Règles d'écriture d'une note. Le statut ne change que par le Workflow « expense » (dont les
 * listeners datent les étapes et tiennent l'historique) ; ce qui est modifiable ou supprimable est
 * décidé par ExpenseStatus::isEditable(). Les entrées sont déjà validées (ExpenseInput, RejectionInput)
 * et le droit sur la note déjà vérifié (ExpenseVoter).
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
        $this->em->persist(new ExpenseEvent($expense, $owner, ExpenseEventType::Created)); // C10
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
            throw PublicHttpException::conflict('Seule une note en brouillon ou rejetée peut être soumise.');
        }
        if (null === $expense->getOwner()->getManager()) {
            // C13 : sans responsable, personne ne pourrait décider de la note
            throw PublicHttpException::conflict('Vous n\'avez pas de responsable : personne ne peut valider vos notes, elles ne peuvent donc pas être soumises.');
        }
        $this->workflow->apply($expense, 'submit');
        $this->em->flush();

        return $expense;
    }

    public function approve(Expense $expense): Expense
    {
        $this->decide($expense, 'approve');

        return $expense;
    }

    /** @param string $reason motif déjà validé (RejectionInput) */
    public function reject(Expense $expense, string $reason): Expense
    {
        $this->decide($expense, 'reject', ['reason' => UnicodeTrim::trim($reason)]);

        return $expense;
    }

    /** @param array<string, mixed> $context */
    private function decide(Expense $expense, string $transition, array $context = []): void
    {
        if (!$this->workflow->can($expense, $transition)) {
            throw PublicHttpException::conflict('Seule une note soumise peut être validée ou rejetée.'); // C6
        }
        $this->workflow->apply($expense, $transition, $context);
        $this->em->flush();
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
            throw PublicHttpException::conflict('Cette note n\'est plus modifiable : seule une note en brouillon ou rejetée peut être modifiée ou supprimée.');
        }
    }

    private function apply(Expense $expense, ExpenseInput $input): void
    {
        $category = $this->categories->findOneBy(['slug' => $input->categorySlug]);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $input->date);
        \assert(null !== $category && false !== $date && null !== $input->label && \is_int($input->amountCents), 'ExpenseInput doit être validé avant.');

        $expense
            ->setLabel(UnicodeTrim::trim($input->label))
            ->setAmountCents($input->amountCents)
            ->setDate($date)
            ->setCategory($category);
    }
}
