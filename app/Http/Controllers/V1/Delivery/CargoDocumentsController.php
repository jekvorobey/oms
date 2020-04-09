<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Services\DeliveryService;
use App\Services\DocumentService;
use App\Services\Dto\Out\DocumentDto;
use Greensight\CommonMsa\Dto\FileDto;
use Greensight\CommonMsa\Services\FileService\FileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class CargoDocumentsController
 * @package App\Http\Controllers\V1\Delivery
 */
class CargoDocumentsController extends Controller
{
    /**
     * Сформировать "Акт приема-передачи по грузу"
     * @param  int  $cargoId
     * @param DeliveryService $deliveryService
     * @param DocumentService $documentService
     * @return JsonResponse
     */
    public function acceptanceAct(
        int $cargoId,
        DeliveryService $deliveryService,
        DocumentService $documentService
    ): JsonResponse {
        $cargo = $deliveryService->getCargo($cargoId);
        if (!$cargo) {
            throw new NotFoundHttpException('cargo not found');
        }

        $documentDto = $documentService->getCargoAcceptanceAct($cargo);

        return $this->getResponse($documentDto);
    }

    /**
     * @param  DocumentDto  $documentDto
     * @return JsonResponse
     */
    protected function getResponse(DocumentDto $documentDto): JsonResponse
    {
        if ($documentDto->success && $documentDto->file_id) {
            /** @var FileService $fileService */
            $fileService = resolve(FileService::class);
            /** @var FileDto $fileDto */
            $fileDto = $fileService->getFiles([$documentDto->file_id])->first();

            return response()->json([
                'absolute_url' => $fileDto->absoluteUrl(),
                'original_name' => $fileDto->original_name,
                'size' => $fileDto->size,
            ]);
        } else {
            throw new HttpException(500, $documentDto->message);
        }
    }
}
