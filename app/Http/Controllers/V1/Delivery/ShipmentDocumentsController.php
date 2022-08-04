<?php

namespace App\Http\Controllers\V1\Delivery;

use App\Models\Delivery\Shipment;
use App\Models\Order\OrderDocument;
use App\Services\DeliveryService;
use App\Services\DocumentService\OrderUPDCreator;
use App\Services\DocumentService\ShipmentAcceptanceActCreator;
use App\Services\DocumentService\ShipmentAssemblingCardCreator;
use App\Services\DocumentService\ShipmentInventoryCreator;
use App\Services\Dto\Out\DocumentDto;
use App\Services\ShipmentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

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

    /**
     * @OA\GET(
     *     path="api/v1/shipments/{id}/documents/generate-upd",
     *     tags={"Заказы"},
     *     description="Сформировать и сохранить УПД",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="204",
     *         description="",
     *     ),
     *     @OA\Response(response="404", description=""),
     * )
     * @throws Throwable
     */
    public function generateUPD(int $shipmentId, OrderUPDCreator $orderUPDCreator): Response
    {
        /** @var Shipment $shipment */
        $shipment = $this->shipmentService->getShipment($shipmentId);

        $documentDto = $orderUPDCreator->setShipment($shipment)->setCustomer($shipment->delivery->order->customer_id)->create();
        if (!$documentDto->success) {
            throw new Exception('UPD not formed');
        }
        $orderUPDCreator->createOrderDocumentRecord($shipment->delivery->order_id, $documentDto->file_id, OrderDocument::UPD_TYPE);
        $orderUPDCreator->createRecordInCustomerDocuments($shipment->delivery->order->customer_id, $documentDto->file_id, $orderUPDCreator->fullTitle());

        return response('', 204);
    }

    /**
     * @OA\Get(
     *     path="api/v1/shipments/{id}/documents/upd",
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
    public function upd(int $shipmentId): JsonResponse
    {
        /** @var Shipment $shipment */
        $shipment = $this->shipmentService->getShipment($shipmentId);
        $documentDto = new DocumentDto(['file_id' => $shipment->upd_file_id]);

        return $this->documentResponse($documentDto);
    }
}
