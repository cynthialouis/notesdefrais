<?php

namespace App\Controller;

use App\Dto\ExpenseView;
use App\Dto\ExpenseViewFactory;
use App\Entity\Expense;
use App\Entity\User;
use App\Security\Voter\ExpenseVoter;
use App\Service\ExpenseManager;
use App\Service\ReceiptStorage;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Justificatif d'une note (spec 005) : un seul par note, déposé ou retiré par son propriétaire tant
 * qu'elle est modifiable, ouvert par ceux qui peuvent la lire.
 */
#[Route('/api/expenses/{id}/receipt', requirements: ['id' => '\d+'])]
#[OA\Tag(name: 'Justificatifs')]
#[OA\Response(response: 401, description: 'Non connecté')]
#[OA\Response(response: 404, description: 'Note inexistante ou non visible')]
final class ReceiptController extends AbstractController
{
    public function __construct(
        private readonly ExpenseManager $expenses,
        private readonly ExpenseViewFactory $views,
    ) {
    }

    #[Route('', name: 'api_receipt_upload', methods: ['POST'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\RequestBody(content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['file'], properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')])))]
    #[OA\Response(response: 200, description: 'Justificatif joint ou remplacé', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 400, description: 'Fichier absent, vide, trop lourd ou d\'un type refusé')]
    #[OA\Response(response: 409, description: 'La note n\'est ni en brouillon ni rejetée')]
    public function upload(
        #[CurrentUser] User $user,
        #[MapEntity(id: 'id')] Expense $expense,
        #[MapUploadedFile(
            constraints: new Assert\File(
                maxSize: '5M',
                binaryFormat: false,
                mimeTypes: ['image/jpeg', 'image/png', 'application/pdf'],
                maxSizeMessage: 'Le fichier dépasse 5 Mo.',
                mimeTypesMessage: 'Formats acceptés : JPEG, PNG ou PDF.',
                disallowEmptyMessage: 'Le fichier est vide.',
                uploadIniSizeErrorMessage: 'Le fichier dépasse 5 Mo.',
                uploadErrorMessage: 'Le fichier n\'a pas pu être reçu.',
            ),
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST,
        )] UploadedFile $file,
    ): JsonResponse {
        return $this->json($this->views->create($this->expenses->attachReceipt($expense, $file), $user));
    }

    #[Route('', name: 'api_receipt_show', methods: ['GET'])]
    #[IsGranted(ExpenseVoter::VIEW, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Le fichier, affiché par le navigateur (inline)', content: [new OA\MediaType(mediaType: 'image/jpeg'), new OA\MediaType(mediaType: 'image/png'), new OA\MediaType(mediaType: 'application/pdf')])]
    public function show(#[MapEntity(id: 'id')] Expense $expense, ReceiptStorage $storage): BinaryFileResponse
    {
        $receipt = $expense->getReceipt() ?? throw new NotFoundHttpException('Aucun justificatif sur cette note.');

        $response = new BinaryFileResponse($storage->path($receipt->getStorageName()), headers: ['Content-Type' => $receipt->getMimeType()], autoEtag: true);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Nom d'origine pour l'enregistrement, sans séparateur de chemin (refusé par makeDisposition)
        $name = str_replace(['/', '\\'], '_', $receipt->getOriginalName());
        $fallback = preg_replace('/[^\x20-\x7e]|[%"]/', '_', $name) ?? 'justificatif';
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $name, $fallback);

        return $response;
    }

    #[Route('', name: 'api_receipt_delete', methods: ['DELETE'])]
    #[IsGranted(ExpenseVoter::OWNER, subject: 'expense', statusCode: Response::HTTP_NOT_FOUND)]
    #[OA\Response(response: 200, description: 'Justificatif retiré', content: new Model(type: ExpenseView::class))]
    #[OA\Response(response: 409, description: 'La note n\'est ni en brouillon ni rejetée')]
    public function delete(#[CurrentUser] User $user, #[MapEntity(id: 'id')] Expense $expense): JsonResponse
    {
        return $this->json($this->views->create($this->expenses->removeReceipt($expense), $user));
    }
}
