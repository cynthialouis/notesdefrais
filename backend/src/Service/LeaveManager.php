<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Règles d'écriture d'une demande de congé (spec 007). Le statut ne change que par le Workflow
 * « leave_request », dont les listeners enregistrent la décision et l'annulation. Les entrées sont
 * déjà validées (LeaveRequestInput, RejectionInput) et le droit sur la demande déjà vérifié
 * (LeaveRequestVoter, ROLE_ADMIN pour l'annulation).
 */
final class LeaveManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Target('leave_request')]
        private readonly WorkflowInterface $workflow,
    ) {
    }
}
