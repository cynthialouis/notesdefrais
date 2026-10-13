<?php

namespace App\Dto;

use App\Entity\ExpenseEvent;
use App\Enum\ExpenseEventType;
use OpenApi\Attributes as OA;

/**
 * Entrée de l'historique d'une note.
 */
#[OA\Schema(required: ['comment'])] // nullable mais toujours présent (Nelmio ne le déduit pas)
final readonly class ExpenseEventView
{
    public function __construct(
        public ExpenseEventType $type,
        public PersonRef $actor,
        /** Motif pour un rejet, null sinon */
        public ?string $comment,
        /** Date et heure ISO 8601 */
        public string $createdAt,
    ) {
    }

    public static function fromEntity(ExpenseEvent $event): self
    {
        return new self($event->getType(), PersonRef::fromUser($event->getActor()), $event->getComment(), $event->getCreatedAt()->format(\DATE_ATOM));
    }
}
