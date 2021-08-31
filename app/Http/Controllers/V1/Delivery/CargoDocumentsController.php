<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Services\DeliveryService;
use App\Services\DocumentService\CargoAcceptanceActCreator;
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
     * @OA\Get(
     *     path="api/v1/cargos/{id}/documents/acceptance-act",
     *     tags={"Акт приема-передачи по грузу"},
     *     description="Сформировать акт приема-передачи по грузу",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="absolute_url"),
     *             @OA\Property(property="original_name"),
     *             @OA\Property(property="size")
     *         )
     *     )
     *  )
     * Сформировать "Акт приема-передачи по грузу"
     */
    public function acceptanceAct(
        int $cargoId,
        DeliveryService $deliveryService,
        CargoAcceptanceActCreator $cargoAcceptanceActCreator
    ): JsonResponse {
        $cargo = $deliveryService->getCargo($cargoId);
        if (!$cargo) {
            throw new NotFoundHttpException('cargo not found');
        }

        $documentDto = $cargoAcceptanceActCreator->setCargo($cargo)->create();

        return $this->getResponse($documentDto);
    }

    protected function getResponse(DocumentDto $documentDto): JsonResponse
    {
        if (!$documentDto->success || !$documentDto->file_id) {
            throw new HttpException(500, $documentDto->message);
        }

        /** @var FileService $fileService */
        $fileService = resolve(FileService::class);
        /** @var FileDto $fileDto */
        $fileDto = $fileService->getFiles([$documentDto->file_id])->first();

        return response()->json([
            'absolute_url' => $fileDto->absoluteUrl(),
            'original_name' => $fileDto->original_name,
            'size' => $fileDto->size,
        ]);
    }
}
