<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Seul endroit qui construit le format d'erreur de l'API : { error: { code, message, fields? } }.
 */
final class ApiError
{
    private const CODES = [
        400 => ['bad_request', 'Requête invalide.'],
        401 => ['unauthorized', 'Authentification requise.'],
        403 => ['forbidden', 'Accès refusé.'],
        404 => ['not_found', 'Ressource introuvable.'],
        405 => ['method_not_allowed', 'Méthode non autorisée.'],
        409 => ['conflict', 'Action impossible dans l\'état actuel.'],
    ];

    /**
     * @param array<string, string> $fields  erreurs par champ, pour une erreur de validation
     * @param array<string, string> $headers
     * @param string|null           $message message propre au cas d'erreur, à la place du message par défaut du statut
     */
    public static function response(int $status, array $fields = [], array $headers = [], ?string $message = null): JsonResponse
    {
        if ([] !== $fields) {
            $error = ['code' => 'validation_failed', 'message' => $message ?? 'Données invalides.', 'fields' => $fields];
        } else {
            [$code, $defaultMessage] = self::CODES[$status] ?? ['internal_error', 'Erreur interne.'];
            $error = ['code' => $code, 'message' => $message ?? $defaultMessage];
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
