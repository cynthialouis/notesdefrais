<?php

namespace App\Stats;

/**
 * Période du tableau de bord (spec 006) : les 12 derniers mois civils, mois en cours compris, en heure
 * de Paris. Une note y entre selon la date de la dépense.
 */
final readonly class StatsPeriod
{
    private const TIMEZONE = 'Europe/Paris';
    private const MONTHS = 12;

    private function __construct(
        /** Premier jour du mois le plus ancien */
        public \DateTimeImmutable $from,
        /** Dernier jour du mois en cours */
        public \DateTimeImmutable $to,
    ) {
    }

    public static function endingAt(\DateTimeImmutable $now): self
    {
        $today = $now->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $currentMonth = $today->modify('first day of this month')->setTime(0, 0);

        return new self(
            $currentMonth->modify(\sprintf('-%d months', self::MONTHS - 1)),
            $currentMonth->modify('last day of this month'),
        );
    }

    /**
     * Les 12 mois de la période, du plus ancien au plus récent, au format AAAA-MM.
     *
     * @return list<string>
     */
    public function months(): array
    {
        $months = [];
        for ($month = $this->from; $month <= $this->to; $month = $month->modify('+1 month')) {
            $months[] = $month->format('Y-m');
        }

        return $months;
    }
}
