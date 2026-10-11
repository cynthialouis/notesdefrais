<?php

namespace App\Validator;

use App\Repository\CategoryRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ExistingCategoryValidator extends ConstraintValidator
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExistingCategory) {
            throw new UnexpectedTypeException($constraint, ExistingCategory::class);
        }

        // Valeur absente : signalée par NotBlank
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) || null === $this->categories->findOneBy(['slug' => $value])) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
