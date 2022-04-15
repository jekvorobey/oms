<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Services\DeliveryService;
use App\Services\DocumentService\ShipmentAcceptanceActCreator;
use App\Services\DocumentService\ShipmentAssemblingCardCreator;
use App\Services\DocumentService\ShipmentInventoryCreator;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentDocumentsController extends DocumentController
{
    protected DeliveryService $deliveryService;
    protected ShipmentService $shipmentService;

    public function __construct(DeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
        $this->shipmentService = resolve(ShipmentService::class);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/documents/acceptance-act",
     *     tags={"Документы"},
     *     description="Сформировать Акт приема-передачи по отправлению",
     *     @OA\Parameter(name="as_pdf", required=false, in="query", @OA\Schema(type="boolean"),
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
        $shipment = $this->shipmentService->getShipment($shipmentId);
        $documentDto = $shipmentAcceptanceActCreator->setShipment($shipment)->setAsPdf($request->as_pdf ?: false)->create();

        return $this->documentResponse($documentDto);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/documents/assembling-card",
     *     tags={"Документы"},
     *     description="Сформировать Карточку сборки отправления",
     *     @OA\Parameter(name="as_pdf", required=false, in="query", @OA\Schema(type="boolean"),
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
        $shipment = $this->shipmentService->getShipment($shipmentId);
        $documentDto = $shipmentAssemblingCardCreator->setShipment($shipment)->setAsPdf($request->as_pdf ?: false)->create();

        return $this->documentResponse($documentDto);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/documents/inventory",
     *     tags={"Документы"},
     *     description="Сформировать Опись отправления заказа",
     *     @OA\Parameter(name="as_pdf", required=false, in="query", @OA\Schema(type="boolean"),
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
        $shipment = $this->shipmentService->getShipment($shipmentId);
        $documentDto = $shipmentInventoryCreator->setShipment($shipment)->setAsPdf($request->as_pdf ?: false)->create();

        return $this->documentResponse($documentDto);
    }
}
