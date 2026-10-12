<?php

namespace App\Security\Voter;

use App\Entity\Expense;
use App\Entity\User;
use App\Enum\ExpenseStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une note précise :
 * - OWNER : son propriétaire (modifier, soumettre, supprimer) ;
 * - VIEW : son propriétaire, ou le responsable du propriétaire dès que la note n'est plus un brouillon
 *   (lecture et historique, spec 003 C11 et C12) ;
 * - DECIDE : le responsable du propriétaire (valider, rejeter). L'état « soumise » est vérifié
 *   par le Workflow (409), pas ici.
 *
 * @extends Voter<string, Expense>
 */
final class ExpenseVoter extends Voter
{
    public const OWNER = 'EXPENSE_OWNER';
    public const VIEW = 'EXPENSE_VIEW';
    public const DECIDE = 'EXPENSE_DECIDE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::OWNER, self::VIEW, self::DECIDE], true) && $subject instanceof Expense;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::OWNER => self::isOwner($subject, $user),
            self::VIEW => self::isOwner($subject, $user) || (self::isResponsible($subject, $user) && ExpenseStatus::Draft !== $subject->getStatus()),
            self::DECIDE => self::isResponsible($subject, $user),
            default => false,
        };
    }

    private static function isOwner(Expense $expense, User $user): bool
    {
        return $expense->getOwner()->getId() === $user->getId();
    }

    /** Le responsable direct du propriétaire (son manager, ou l'admin pour un manager). */
    private static function isResponsible(Expense $expense, User $user): bool
    {
        return null !== $expense->getOwner()->getManager() && $expense->getOwner()->getManager()->getId() === $user->getId();
    }
}
