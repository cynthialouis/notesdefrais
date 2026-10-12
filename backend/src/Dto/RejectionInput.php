<?php

namespace App\Dto;

use App\Text\UnicodeTrim;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/expenses/{id}/reject. Longueur comptée en caractères après UnicodeTrim, comme le front.
 */
final readonly class RejectionInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le motif est obligatoire.', normalizer: [UnicodeTrim::class, 'trim'])]
        #[Assert\Length(min: 5, max: 1000, minMessage: 'Le motif doit faire au moins {{ limit }} caractères.', maxMessage: 'Le motif doit faire au plus {{ limit }} caractères.', normalizer: [UnicodeTrim::class, 'trim'])]
        public ?string $reason = null,
    ) {
    }
}
