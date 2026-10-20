<?php

namespace App\Security\Voter;

use App\Entity\LeaveRequest;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une demande de congé précise (spec 007, research.md D7) :
 * - PARTY : son demandeur ou son responsable, seuls concernés par une décision (404 pour tout autre) ;
 * - DECIDE : son responsable (accepter, refuser ; 403 pour le demandeur) ;
 * - REQUESTER : son demandeur (retirer).
 * L'état requis par chaque action est vérifié par le Workflow (409), pas ici. Ce que chacun voit (C15 à C18)
 * est réglé par les requêtes des listes (LeaveRequestRepository) : aucune route ne lit une demande seule.
 *
 * @extends Voter<string, LeaveRequest>
 */
final class LeaveRequestVoter extends Voter
{
    public const PARTY = 'LEAVE_PARTY';
    public const DECIDE = 'LEAVE_DECIDE';
    public const REQUESTER = 'LEAVE_REQUESTER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::PARTY, self::DECIDE, self::REQUESTER], true) && $subject instanceof LeaveRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::PARTY => $subject->isRequestedBy($user) || $subject->isDecidedBy($user),
            self::DECIDE => $subject->isDecidedBy($user),
            self::REQUESTER => $subject->isRequestedBy($user),
            default => false,
        };
    }
}
