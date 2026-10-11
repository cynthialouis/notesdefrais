<?php

namespace App\EventListener;

use App\Http\ApiError;
use App\Http\PublicHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
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
    /**
     * Messages produits par Symfony lui-même (erreurs de type à la désérialisation, MapRequestPayload
     * et MapQueryString), en anglais : remplacés par un message en français.
     */
    private const FRAMEWORK_MESSAGES = [
        'This value was of an unexpected type.' => 'Valeur invalide.',
        'This value should be of type {{ type }}.' => 'Valeur invalide.',
        'This attribute was not expected.' => 'Champ inattendu.',
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $isHttp = $exception instanceof HttpExceptionInterface;

        $event->setResponse(ApiError::response(
            $isHttp ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR,
            self::fields($exception),
            $isHttp ? $exception->getHeaders() : [],
            $exception instanceof PublicHttpException ? $exception->getMessage() : null,
        ));
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
                    $fields[$violation->getPropertyPath()] ??= self::FRAMEWORK_MESSAGES[$violation->getMessageTemplate()] ?? (string) $violation->getMessage();
                }
            }
            if ($e instanceof PartialDenormalizationException) {
                foreach ($e->getNotNormalizableValueErrors() as $error) {
                    $fields[(string) $error->getPath()] ??= 'Type invalide.';
                }
            }
        }

        return $fields;
    }
}
