<?php

namespace App\Security;

use App\Dto\LoginInput;
use App\Entity\User;
use App\Http\ApiError;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Connexion de démo, sans mot de passe : POST /api/auth/login { "userId": <int> }.
 *
 * - corps invalide → 400 avec le détail des champs (via ApiExceptionListener) ;
 * - compte inexistant → 401 générique, qui ne révèle pas si le compte existe ;
 * - succès → session ouverte (ou remplacée) et utilisateur courant en JSON.
 *
 * Sert aussi de point d'entrée : une route protégée appelée sans session répond 401.
 */
final class DemoLoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly UserRepository $users,
        private readonly UserViewFactory $userViews,
    ) {
    }

    public function supports(Request $request): bool
    {
        return '/api/auth/login' === $request->getPathInfo() && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $userId = $this->validInput($request)->userId;
        \assert(null !== $userId);

        return new SelfValidatingPassport(new UserBadge((string) $userId, fn (): ?User => $this->users->find($userId)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $user = $token->getUser();
        \assert($user instanceof User);

        return new JsonResponse($this->serializer->serialize($this->userViews->create($user), 'json'), Response::HTTP_OK, [], true);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Même message quel que soit le motif : on ne révèle pas si le compte existe (C11)
        return ApiError::response(Response::HTTP_UNAUTHORIZED, message: 'Connexion refusée : compte inconnu.');
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return ApiError::response(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Désérialise et valide le corps. Les exceptions levées ici ne sont pas des échecs
     * d'authentification : elles remontent jusqu'à ApiExceptionListener (400).
     */
    private function validInput(Request $request): LoginInput
    {
        try {
            $input = $this->serializer->deserialize($request->getContent(), LoginInput::class, 'json', [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (NotEncodableValueException|PartialDenormalizationException $e) {
            throw new BadRequestHttpException('Corps de requête invalide.', $e);
        }

        $violations = $this->validator->validate($input);
        if (\count($violations) > 0) {
            throw new BadRequestHttpException('Données invalides.', new ValidationFailedException($input, $violations));
        }

        return $input;
    }
}
