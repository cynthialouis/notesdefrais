<?php

namespace App\Security\Voter;

use App\Entity\Expense;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une note précise. Feature 002 : seul le propriétaire y a accès.
 * (La feature 003 ajoutera les droits du manager de l'équipe.)
 *
 * @extends Voter<string, Expense>
 */
final class ExpenseVoter extends Voter
{
    public const OWNER = 'EXPENSE_OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OWNER === $attribute && $subject instanceof Expense;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->getOwner()->getId() === $user->getId();
    }
}
