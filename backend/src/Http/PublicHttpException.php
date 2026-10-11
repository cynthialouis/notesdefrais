<?php

namespace App\Http;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Erreur HTTP dont le message est écrit pour l'utilisateur : ApiExceptionListener le renvoie tel quel.
 * Les autres exceptions ne renvoient que le message générique de leur statut (aucune fuite de détail interne).
 */
final class PublicHttpException extends HttpException
{
    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }
}
