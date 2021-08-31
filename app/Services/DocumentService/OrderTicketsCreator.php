<?php

namespace App\Services\DocumentService;

use App\Models\Order\Order;
use App\Services\OrderService;
use mikehaertl\wkhtmlto\Pdf;

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

    protected function createTemplate(): Pdf
    {
        if (!$this->order->isPaid()) {
            throw new \Exception('Order is not paid');
        }

        $pdf = new Pdf();

        $orderInfoDto = $this->orderService->getPublicEventsOrderInfo($this->order, true, $this->basketItemId);

        if (!$orderInfoDto) {
            throw new \Exception('Order is not PublicEventOrder');
        }

        $html = view('pdf::ticket', [
            'order' => $orderInfoDto,
        ])->render();

        $pdf->addPage($html, [], Pdf::TYPE_HTML);

        return $pdf;
    }

    /**
     * @param Pdf $template
     */
    protected function saveTmpDoc($template, string $path): void
    {
        $template->saveAs($path);
    }

    protected function resultDocSuffix(): string
    {
        return $this->order->number;
    }
}
