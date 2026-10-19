<?php

namespace App\Security\Voter;

use App\Entity\LeaveRequest;
use App\Entity\User;
use App\Enum\LeaveStatus;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une demande de congé précise (spec 007, research.md D7) :
 * - VIEW : son demandeur ; tout le monde si elle est acceptée ; son responsable et l'admin si elle
 *   est en attente (C15, C17). Refusée, retirée ou annulée, elle n'est visible que de son demandeur ;
 * - PARTY : son demandeur ou son responsable, seuls concernés par une décision (404 pour tout autre) ;
 * - DECIDE : son responsable (accepter, refuser ; 403 pour le demandeur) ;
 * - REQUESTER : son demandeur (retirer).
 * L'état requis par chaque action est vérifié par le Workflow (409), pas ici.
 *
 * @extends Voter<string, LeaveRequest>
 */
final class LeaveRequestVoter extends Voter
{
    public const VIEW = 'LEAVE_VIEW';
    public const PARTY = 'LEAVE_PARTY';
    public const DECIDE = 'LEAVE_DECIDE';
    public const REQUESTER = 'LEAVE_REQUESTER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::PARTY, self::DECIDE, self::REQUESTER], true) && $subject instanceof LeaveRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::PARTY => $subject->isRequestedBy($user) || $subject->isDecidedBy($user),
            self::DECIDE => $subject->isDecidedBy($user),
            self::REQUESTER => $subject->isRequestedBy($user),
            default => false,
        };
    }

    private function canView(LeaveRequest $leave, User $user): bool
    {
        return match (true) {
            $leave->isRequestedBy($user), LeaveStatus::Accepted === $leave->getStatus() => true,
            LeaveStatus::Pending === $leave->getStatus() => $leave->isDecidedBy($user) || UserRole::Admin === $user->getRole(),
            default => false,
        };
    }
}
