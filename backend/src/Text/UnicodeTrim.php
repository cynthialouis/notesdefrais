<?php

namespace App\Text;

/**
 * Retire les blancs de début et de fin, Unicode compris (espaces insécables, U+2028, U+FEFF…),
 * comme String.prototype.trim() en JavaScript : le front et le back retirent les mêmes caractères.
 * (trim() de PHP ne retire que les blancs ASCII.)
 */
final class UnicodeTrim
{
    public static function trim(string $value): string
    {
        return preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $value) ?? $value;
    }
}
