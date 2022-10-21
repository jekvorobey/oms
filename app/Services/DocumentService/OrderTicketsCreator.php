<?php

namespace App\Services\DocumentService;

use App\Models\Order\Order;
use App\Services\OrderService;
use Exception;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Pim\Core\PimException;
use RuntimeException;

class OrderTicketsCreator extends DocumentCreator
{
    protected OrderService $orderService;

    protected Order $order;
    protected ?int $basketItemId = null;

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
            throw new RuntimeException('Order is not paid');
        }

        $orderInfoDto = $this->orderService->getPublicEventsOrderInfo($this->order, true, $this->basketItemId);

        if (!$orderInfoDto) {
            throw new RuntimeException('Order is not PublicEventOrder');
        }

        $path = $this->generateDocumentPath();

        $pdf = PDF::loadView('pdf::ticket', [
            'order' => $orderInfoDto,
        ]);
        $pdf->setOption('enable-local-file-access', true);

        $pdf->save($path, true);

        return $path;
    }

    protected function resultDocSuffix(): string
    {
        return $this->order->number;
    }
}
