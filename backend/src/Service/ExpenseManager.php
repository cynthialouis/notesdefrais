<?php

namespace App\Service;

use App\Dto\ExpenseInput;
use App\Entity\Expense;
use App\Entity\ExpenseEvent;
use App\Entity\Receipt;
use App\Entity\User;
use App\Enum\ExpenseEventType;
use App\Http\PublicHttpException;
use App\Repository\CategoryRepository;
use App\Text\UnicodeTrim;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        private readonly PolicyChecker $policyChecker,
        private readonly ReceiptStorage $receipts,
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
        if ($this->policyChecker->check($expense)->receiptMissing) {
            // Spec 005, C8 : vérifié après l'état et le responsable
            throw PublicHttpException::conflict('Un justificatif est obligatoire pour la catégorie '.$expense->getCategory()->getLabel().' : joignez-le avant de soumettre la note.');
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
        $storageName = $expense->getReceipt()?->getStorageName();
        $this->em->remove($expense);
        $this->em->flush();
        // Fichier effacé après la base : jamais de justificatif en base sans son fichier (spec 005, C11)
        if (null !== $storageName) {
            $this->receipts->delete($storageName);
        }
    }

    /**
     * Joint ou remplace le justificatif (spec 005, C1, C6, C7). Le fichier est déjà validé (type d'après
     * le contenu, taille) ; l'ancien fichier n'est effacé qu'une fois le nouveau enregistré en base.
     */
    public function attachReceipt(Expense $expense, UploadedFile $file): Expense
    {
        $this->assertEditable($expense);
        $mimeType = (string) $file->getMimeType();
        $sizeBytes = (int) $file->getSize();
        $previous = $expense->getReceipt()?->getStorageName();

        $storageName = $this->receipts->store($file);
        try {
            $expense->setReceipt(new Receipt(self::displayName($file->getClientOriginalName()), $mimeType, $sizeBytes, $storageName));
            $this->em->flush();
        } catch (\Throwable $error) {
            $this->receipts->delete($storageName);
            throw $error;
        }
        if (null !== $previous) {
            $this->receipts->delete($previous);
        }

        return $expense;
    }

    /** Retire le justificatif (spec 005, C6, C7) ; 404 si la note n'en a pas. */
    public function removeReceipt(Expense $expense): Expense
    {
        $this->assertEditable($expense);
        $receipt = $expense->getReceipt() ?? throw new NotFoundHttpException('Aucun justificatif sur cette note.');
        $storageName = $receipt->getStorageName(); // lu avant le flush : la ligne est supprimée (orphanRemoval)
        $expense->setReceipt(null);
        $this->em->flush();
        $this->receipts->delete($storageName);

        return $expense;
    }

    /** Nom d'origine tronqué à la taille de sa colonne (255 caractères), en gardant l'extension. */
    private static function displayName(string $name): string
    {
        if (mb_strlen($name) <= 255) {
            return $name;
        }
        $extension = pathinfo($name, \PATHINFO_EXTENSION);
        $suffix = '' !== $extension && mb_strlen($extension) <= 10 ? '.'.$extension : '';

        return mb_substr($name, 0, 255 - mb_strlen($suffix)).$suffix;
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
