<?php

namespace App\Tests\Enum;

use App\Enum\ExpenseStatus;
use PHPUnit\Framework\TestCase;

/**
 * ExpenseStatus::isEditable() dit seul quels états se modifient (plan de la spec 002, C5, C7, C12).
 */
final class ExpenseStatusTest extends TestCase
{
    public function testC5SeulLeBrouillonEstModifiable(): void
    {
        self::assertTrue(ExpenseStatus::Draft->isEditable());
    }

    public function testC7LesAutresEtatsNeSontPasModifiables(): void
    {
        self::assertFalse(ExpenseStatus::Submitted->isEditable());
        self::assertFalse(ExpenseStatus::Approved->isEditable());
        self::assertFalse(ExpenseStatus::Rejected->isEditable());
    }

    public function testContratLesValeursCorrespondentAuContratDeLApi(): void
    {
        self::assertSame(
            ['draft', 'submitted', 'approved', 'rejected'],
            array_map(static fn (ExpenseStatus $s): string => $s->value, ExpenseStatus::cases()),
        );
    }
}
