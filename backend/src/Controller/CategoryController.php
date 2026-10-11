<?php

namespace App\Controller;

use App\Dto\CategoryView;
use App\Repository\CategoryRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController extends AbstractController
{
    #[Route('/api/categories', name: 'api_categories', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Catégories de dépense', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: CategoryView::class))))]
    #[OA\Response(response: 401, description: 'Non connecté')]
    public function __invoke(CategoryRepository $categories): JsonResponse
    {
        return $this->json(array_map(
            static fn ($category): CategoryView => new CategoryView($category->getSlug(), $category->getLabel()),
            $categories->findAllOrdered(),
        ));
    }
}
