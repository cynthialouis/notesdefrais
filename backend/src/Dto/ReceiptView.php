<?php

namespace App\Dto;

use App\Entity\Receipt;

/**
 * Justificatif tel qu'il apparaît dans une note (spec 005) ; le fichier s'ouvre par GET /api/expenses/{id}/receipt.
 */
final readonly class ReceiptView
{
    public function __construct(
        /** Nom d'origine, pour l'affichage */
        public string $name,
        public string $mimeType,
        public int $sizeBytes,
    ) {
    }

    public static function fromEntity(Receipt $receipt): self
    {
        return new self($receipt->getOriginalName(), $receipt->getMimeType(), $receipt->getSizeBytes());
    }
}
