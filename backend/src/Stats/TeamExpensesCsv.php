<?php

namespace App\Stats;

use App\Entity\Expense;

/**
 * Export CSV des notes validées de l'équipe (spec 006, C6, C7, C9), pour un tableur français :
 * BOM UTF-8, « ; », fins de ligne CRLF, montants à virgule, dates JJ/MM/AAAA en heure de Paris.
 * Échappement RFC 4180 ; un champ qui commence par un caractère de formule est neutralisé par une
 * apostrophe, pour qu'un libellé saisi par un salarié ne s'exécute pas dans le tableur.
 */
final class TeamExpensesCsv
{
    private const BOM = "\u{FEFF}";
    private const HEADER = ['Date', 'Salarié', 'Libellé', 'Catégorie', 'Montant', 'Date de validation', 'Validée par'];
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /** @param list<Expense> $expenses notes déjà filtrées et triées */
    public function build(array $expenses): string
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $lines = [self::line(self::HEADER)];
        foreach ($expenses as $expense) {
            $lines[] = self::line([
                $expense->getDate()->format('d/m/Y'),
                $expense->getOwner()->getName(),
                $expense->getLabel(),
                $expense->getCategory()->getLabel(),
                self::euros($expense->getAmountCents()),
                $expense->getDecidedAt()?->setTimezone($paris)->format('d/m/Y') ?? '',
                $expense->getDecidedBy()?->getName() ?? '',
            ]);
        }

        return self::BOM.implode('', $lines);
    }

    /** @param list<string> $fields */
    private static function line(array $fields): string
    {
        return implode(';', array_map(self::field(...), $fields))."\r\n";
    }

    private static function field(string $value): string
    {
        if ('' !== $value && \in_array($value[0], self::FORMULA_PREFIXES, true)) {
            $value = "'".$value;
        }
        if (strpbrk($value, ";\"\r\n") !== false) {
            $value = '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    /** 123450 → « 1234,50 » : centimes entiers, sans flottant ni séparateur de milliers. */
    private static function euros(int $cents): string
    {
        return \sprintf('%d,%02d', intdiv($cents, 100), $cents % 100);
    }
}
