<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Date au format Y-m-d qui n'est pas antérieure à aujourd'hui, en Europe/Paris (spec 007, C2).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class NotInPast extends Constraint
{
    public string $message = 'La date ne peut pas être dans le passé.';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
