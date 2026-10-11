<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Date au format Y-m-d qui n'est pas postérieure à aujourd'hui, en Europe/Paris.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class NotInFuture extends Constraint
{
    public string $message = 'La date ne peut pas être dans le futur.';
}
