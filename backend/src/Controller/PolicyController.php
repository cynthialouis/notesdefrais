<?php

namespace App\Controller;

use App\Dto\CategoryView;
use App\Dto\PolicyInput;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Politique de dépense d'une catégorie, réglée par l'admin (spec 004, C2, C3).
 */
final class PolicyController extends AbstractController
{
    #[Route('/api/policies/{slug}', name: 'api_policies_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Response(response: 200, description: 'Politique enregistrée', content: new Model(type: CategoryView::class))]
    #[OA\Response(response: 400, description: 'Plafond ou justificatif invalide')]
    #[OA\Response(response: 401, description: 'Non connecté')]
    #[OA\Response(response: 403, description: 'Réservé à l\'admin')]
    #[OA\Response(response: 404, description: 'Catégorie inconnue')]
    public function update(
        #[MapEntity(mapping: ['slug' => 'slug'])] Category $category,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] PolicyInput $input,
        EntityManagerInterface $em,
    ): JsonResponse {
        \assert((null === $input->maxAmountCents || \is_int($input->maxAmountCents)) && \is_bool($input->receiptRequired), 'PolicyInput doit être validé avant.');

        $category->setMaxAmountCents($input->maxAmountCents)->setReceiptRequired($input->receiptRequired);
        $em->flush();

        return $this->json(CategoryView::fromEntity($category));
    }
}
