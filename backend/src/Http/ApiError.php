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
     */
    public static function response(int $status, array $fields = [], array $headers = []): JsonResponse
    {
        if ([] !== $fields) {
            $error = ['code' => 'validation_failed', 'message' => 'Données invalides.', 'fields' => $fields];
        } else {
            [$code, $message] = self::CODES[$status] ?? ['internal_error', 'Erreur interne.'];
            $error = ['code' => $code, 'message' => $message];
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
