<?php

namespace App\Tests\Enum;

use App\Enum\ExpenseStatus;
use PHPUnit\Framework\TestCase;

/**
 * ExpenseStatus::isEditable() dit seul quels états se modifient (plan de la spec 002, C5, C7, C12 ;
 * spec 003 C8 : une note rejetée redevient modifiable).
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
    }

    public function testC8UneNoteRejeteeEstModifiable(): void
    {
        // Spec 003 C8 : le propriétaire corrige puis resoumet une note rejetée.
        self::assertTrue(ExpenseStatus::Rejected->isEditable());
    }

    public function testContratLesValeursCorrespondentAuContratDeLApi(): void
    {
        self::assertSame(
            ['draft', 'submitted', 'approved', 'rejected'],
            array_map(static fn (ExpenseStatus $s): string => $s->value, ExpenseStatus::cases()),
        );
    }
}
