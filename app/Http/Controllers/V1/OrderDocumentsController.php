<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\V1\Delivery\DocumentController;
use App\Models\Order\Order;
use App\Models\Order\OrderDocument;
use App\Services\DocumentService\OrderInvoiceOfferCreator;
use App\Services\DocumentService\OrderUPDCreator;
use App\Services\Dto\Out\DocumentDto;
use App\Services\OrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class OrderDocumentsController extends DocumentController
{
    protected OrderService $orderService;

    public function __construct()
    {
        $this->orderService = resolve(OrderService::class);
    }

    /**
     * @OA\GET(
     *     path="api/v1/orders/{id}/documents/generate-invoice-offer",
     *     tags={"Заказы"},
     *     description="Сформировать и сохранить счет-оферту",
     *     @OA\Parameter(name="id", required=true, in="path", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response="204",
     *         description="",
     *     ),
     *     @OA\Response(response="404", description=""),
     * )
     * @throws Throwable
     */
    public function generateInvoiceOffer(int $id, OrderInvoiceOfferCreator $invoiceOfferCreator): Response
    {
        /** @var Order $order */
        $order = Order::query()->where('id', $id)->with('basket.items')->first();
        if (!$order) {
            throw new Exception("Order by id={$id} not found");
        }

        $documentDto = $invoiceOfferCreator->setOrder($order)->setCustomer($order->customer_id)->create();
        if (!$documentDto->success) {
            throw new Exception('Invoice offer not formed');
        }
        $invoiceOfferCreator->createOrderDocumentRecord($order->id, $documentDto->file_id, OrderDocument::INVOICE_OFFER_TYPE);
        $invoiceOfferCreator->createRecordInCustomerDocuments($order->customer_id, $documentDto->file_id, $invoiceOfferCreator->title());

        return response('', 204);
    }

    /**
     * @OA\GET(
     *     path="api/v1/orders/{id}/upd",
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
    public function generateUPD(int $id, OrderUPDCreator $orderUPDCreator): Response
    {
        /** @var Order $order */
        $order = Order::query()->where('id', $id)->with('basket.items')->first();
        if (!$order) {
            throw new Exception("Order by id={$id} not found");
        }

        $documentDto = $orderUPDCreator->setOrder($order)->setCustomer($order->customer_id)->create();
        if (!$documentDto->success) {
            throw new Exception('UPD not formed');
        }
        $orderUPDCreator->createOrderDocumentRecord($order->id, $documentDto->file_id, OrderDocument::UPD_TYPE);
        $orderUPDCreator->createRecordInCustomerDocuments($order->customer_id, $documentDto->file_id, $orderUPDCreator->fullTitle());

        return response('', 204);
    }

    /**
     * @OA\Get(
     *     path="api/v1/orders/{id}/documents",
     *     tags={"Заказы"},
     *     description="Получить документы",
     *     @OA\RequestBody(
     *      @OA\JsonContent(
     *          @OA\Property(property="type", type="string"),
     *      ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/OrderDocument"))
     *         )
     *     ),
     *     @OA\Response(response="404", description="documents not found"),
     * )
     */
    public function documents(int $id, Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'type' => 'sometimes|string',
        ]);
        $documents = OrderDocument::query()->where('order_id', $id);
        if (isset($data['type'])) {
            $documents->where('type', $data['type']);
        }
        $documents->get();

        return response()->json(['items' => $documents]);
    }

    /**
     * @OA\Get(
     *     path="api/v1/orders/{id}/documents/invoice-offer",
     *     tags={"Документы"},
     *     description="Получить счет-оферту по договору",
     *     @OA\Response(response="200", description="",
     *          @OA\JsonContent(
     *             @OA\Property(property="absolute_url", type="string"),
     *             @OA\Property(property="original_name", type="string"),
     *             @OA\Property(property="size", type="string"),
     *         )
     *     ),
     *     @OA\Response(response="404", description="document not found"),
     *     @OA\Response(response="500", description="bad request")
     * )
     * Получить "Счет-оферту по договору"
     */
    public function invoiceOffer(int $id): JsonResponse
    {
        /** @var OrderDocument $document */
        $document = OrderDocument::query()
            ->where('order_id', $id)
            ->where('type', OrderDocument::INVOICE_OFFER_TYPE)
            ->firstOrFail();
        $documentDto = new DocumentDto(['file_id' => $document->file_id, 'success' => true]);

        return $this->documentResponse($documentDto);
    }
}
