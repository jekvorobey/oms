<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Services\DeliveryService;
use App\Services\DocumentService\ShipmentAcceptanceActCreator;
use App\Services\DocumentService\ShipmentAssemblingCardCreator;
use App\Services\DocumentService\ShipmentInventoryCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ShipmentDocumentsController
 * @package App\Http\Controllers\V1\Delivery
 */
class ShipmentDocumentsController extends DocumentController
{
    protected DeliveryService $deliveryService;

    public function __construct(DeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
    }

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
        Request $request,
        ShipmentAcceptanceActCreator $shipmentAcceptanceActCreator
    ): JsonResponse {
        $shipment = $this->deliveryService->getShipment($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }

        $documentDto = $shipmentAcceptanceActCreator->setShipment($shipment)->setAsPdf($request->as_pdf ?: false)->create();

        return $this->documentResponse($documentDto);
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
        Request $request,
        ShipmentAssemblingCardCreator $shipmentAssemblingCardCreator
    ): JsonResponse {
        $shipment = $this->deliveryService->getShipment($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }

        $documentDto = $shipmentAssemblingCardCreator->setShipment($shipment)->setAsPdf($request->as_pdf ?: false)->create();

        return $this->documentResponse($documentDto);
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
        Request $request,
        ShipmentInventoryCreator $shipmentInventoryCreator
    ): JsonResponse {
        $shipment = $this->deliveryService->getShipment($shipmentId);
        if (!$shipment) {
            throw new NotFoundHttpException('shipment not found');
        }

        $documentDto = $shipmentInventoryCreator->setShipment($shipment)->setAsPdf($request->as_pdf ?: false)->create();

        return $this->documentResponse($documentDto);
    }
}
