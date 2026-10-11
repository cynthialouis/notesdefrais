<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Slug d'une catégorie connue du serveur.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ExistingCategory extends Constraint
{
    public string $message = 'Catégorie inconnue.';
}
