<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Http\Controllers\Controller;
use App\Services\DeliveryService;
use App\Services\DocumentService\ShipmentAcceptanceActCreator;
use App\Services\DocumentService\ShipmentAssemblingCardCreator;
use App\Services\DocumentService\ShipmentInventoryCreator;
use App\Services\Dto\Out\DocumentDto;
use Greensight\CommonMsa\Dto\FileDto;
use Greensight\CommonMsa\Services\FileService\FileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ShipmentDocumentsController
 * @package App\Http\Controllers\V1\Delivery
 */
class ShipmentDocumentsController extends Controller
{
    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/documents/acceptance-act",
     *     tags={"Документы"},
     *     description="Сформировать Акт приема-передачи по отправлению",
     *     @OA\Response(response="200", description="",
     *          @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Сформировать "Акт приема-передачи по отправлению"
     */
    public function acceptanceAct(
        int $shipmentId,
        DeliveryService $deliveryService,
        ShipmentAcceptanceActCreator $shipmentAcceptanceActCreator
    ): JsonResponse {
        $shipment = $deliveryService->getShipment($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }

        $documentDto = $shipmentAcceptanceActCreator->setShipment($shipment)->create();

        return $this->getResponse($documentDto);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/documents/assembling-card",
     *     tags={"Документы"},
     *     description="Сформировать Карточку сборки отправления",
     *     @OA\Response(response="200", description="",
     *          @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Сформировать "Карточка сборки отправления"
     */
    public function assemblingCard(
        int $shipmentId,
        DeliveryService $deliveryService,
        ShipmentAssemblingCardCreator $shipmentAssemblingCardCreator
    ): JsonResponse {
        $shipment = $deliveryService->getShipment($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }

        $documentDto = $shipmentAssemblingCardCreator->setShipment($shipment)->create();

        return $this->getResponse($documentDto);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/documents/inventory",
     *     tags={"Документы"},
     *     description="Сформировать Опись отправления заказа",
     *     @OA\Response(response="200", description="",
     *          @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="404", description="shipment not found"),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Сформировать "Опись отправления заказа"
     */
    public function inventory(
        int $shipmentId,
        DeliveryService $deliveryService,
        ShipmentInventoryCreator $shipmentInventoryCreator
    ): JsonResponse {
        $shipment = $deliveryService->getShipment($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }

        $documentDto = $shipmentInventoryCreator->setShipment($shipment)->create();

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
