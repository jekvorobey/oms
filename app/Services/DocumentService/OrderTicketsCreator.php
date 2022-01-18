<?php

namespace App\Services\DocumentService;

use App\Models\Order\Order;
use App\Services\OrderService;
use Exception;
use PDF;
use Pim\Core\PimException;

class OrderTicketsCreator extends DocumentCreator
{
    protected OrderService $orderService;

    protected Order $order;
    protected ?int $basketItemId;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function setBasketItemId(?int $basketItemId): self
    {
        $this->basketItemId = $basketItemId;

        return $this;
    }

    public function documentName(): string
    {
        return 'order-tickets.pdf';
    }

    /**
     * @throws PimException
     * @throws Exception
     */
    protected function createDocument(): string
    {
        if (!$this->order->isPaid()) {
            throw new Exception('Order is not paid');
        }

        $orderInfoDto = $this->orderService->getPublicEventsOrderInfo($this->order, true, $this->basketItemId);

        if (!$orderInfoDto) {
            throw new Exception('Order is not PublicEventOrder');
        }

        $path = $this->generateDocumentPath();

        $pdf = PDF::loadView('pdf::ticket', [
            'order' => $orderInfoDto,
        ]);

        $pdf->save($path, true);

        return $path;
    }

    protected function resultDocSuffix(): string
    {
        return $this->order->number;
    }
}
