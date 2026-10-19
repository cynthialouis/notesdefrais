<?php

namespace App\Tests\Support;

use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Base des tests des routes de congés (spec 007).
 *
 * L'horloge est figée au 10/11/2026 à midi, heure de Paris : « aujourd'hui » vaut TODAY pour le serveur
 * (validation du début, congés en cours ou à venir) et les instants posés par le serveur (createdAt,
 * decidedAt, cancelledAt) valent exactement NOW. Un test peut refiger l'horloge avec self::mockTime().
 */
abstract class LeaveTestCase extends ApiTestCase
{
    use ClockSensitiveTrait;

    protected const TODAY = '2026-11-10';
    protected const NOW = '2026-11-10 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(new \DateTimeImmutable(self::NOW, new \DateTimeZone('Europe/Paris')));
    }

    /**
     * Jour décalé de `$offset` jours par rapport à TODAY, au format Y-m-d (ex. day(-1) = hier).
     */
    protected function day(int $offset): string
    {
        return (new \DateTimeImmutable(self::TODAY))->modify(\sprintf('%+d days', $offset))->format('Y-m-d');
    }

    /** Horodatage Unix de NOW (heure de Paris). */
    protected function nowTimestamp(): int
    {
        return (new \DateTimeImmutable(self::NOW, new \DateTimeZone('Europe/Paris')))->getTimestamp();
    }

    /** Vérifie qu'un instant ISO 8601 de la réponse vaut NOW. */
    protected function assertIsNow(mixed $iso): void
    {
        self::assertIsString($iso);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $iso);
        self::assertSame($this->nowTimestamp(), (new \DateTimeImmutable($iso))->getTimestamp());
    }

    /**
     * Vérifie un message 409 explicite : propre au cas, pas le message générique du statut.
     *
     * @return string le message
     */
    protected function assertExplicitConflict(): string
    {
        $error = $this->assertErrorResponse(409);
        self::assertArrayNotHasKey('fields', $error);
        self::assertNotSame('Action impossible dans l\'état actuel.', $error['message'], 'Le message doit être propre au cas.');

        return $error['message'];
    }

    /**
     * Identifiants d'une liste, dans l'ordre de la réponse.
     *
     * @param list<array<mixed>> $list
     *
     * @return list<int>
     */
    protected function ids(array $list): array
    {
        return array_map(static fn (array $view): int => $view['id'], $list);
    }
}
