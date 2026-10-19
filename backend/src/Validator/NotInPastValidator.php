<?php

namespace App\Validator;

use Psr\Clock\ClockInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NotInPastValidator extends ConstraintValidator
{
    private const TIMEZONE = 'Europe/Paris';

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotInPast) {
            throw new UnexpectedTypeException($constraint, NotInPast::class);
        }

        // Format et date invalides sont signalés par la contrainte Date
        if (!\is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return;
        }

        // Deux dates Y-m-d se comparent comme des chaînes
        $today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d');
        if ($value < $today) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
