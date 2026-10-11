<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Met toute erreur d'une route /api au format unique { error: { code, message, fields? } }.
 * Les messages sont génériques : aucun détail interne (exception, SQL, chemin) n'est renvoyé.
 *
 * Priorité -64 : après la journalisation des erreurs (0), qui serait sinon court-circuitée,
 * et avant le rendu d'erreur par défaut de Symfony (-128).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final class ApiExceptionListener
{
    private const CODES = [
        400 => ['bad_request', 'Requête invalide.'],
        401 => ['unauthorized', 'Authentification requise.'],
        403 => ['forbidden', 'Accès refusé.'],
        404 => ['not_found', 'Ressource introuvable.'],
        405 => ['method_not_allowed', 'Méthode non autorisée.'],
        409 => ['conflict', 'Action impossible dans l\'état actuel.'],
        422 => ['validation_failed', 'Données invalides.'],
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        [$code, $message] = self::CODES[$status] ?? ['internal_error', 'Erreur interne.'];
        $error = ['code' => $code, 'message' => $message];

        $fields = self::fields($exception);
        if ([] !== $fields) {
            $error = ['code' => 'validation_failed', 'message' => 'Données invalides.', 'fields' => $fields];
        }

        $event->setResponse(new JsonResponse(['error' => $error], $status, $headers));
    }

    /**
     * Erreurs par champ, si l'exception (ou l'une de ses causes) vient de la validation ou de la désérialisation.
     *
     * @return array<string, string>
     */
    private static function fields(\Throwable $exception): array
    {
        $fields = [];
        for ($e = $exception; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof ValidationFailedException) {
                foreach ($e->getViolations() as $violation) {
                    $fields[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
                }
            }
            if ($e instanceof PartialDenormalizationException) {
                foreach ($e->getErrors() as $error) {
                    $fields[(string) $error->getPath()] ??= 'Type invalide.';
                }
            }
        }

        return $fields;
    }
}
