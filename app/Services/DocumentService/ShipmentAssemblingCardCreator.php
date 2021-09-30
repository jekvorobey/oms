<?php

namespace App\Services\DocumentService;

use App\Models\Delivery\Shipment;
use App\Services\DeliveryService;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;
use Greensight\Logistics\Services\ListsService\ListsService;
use PhpOffice\PhpWord\TemplateProcessor;
use Pim\Dto\Product\ProductDto;

class ShipmentAssemblingCardCreator extends TemplatedDocumentCreator
{
    protected DeliveryService $deliveryService;
    protected ListsService $listsService;

    protected Shipment $shipment;

    public function __construct(DeliveryService $deliveryService, ListsService $listsService)
    {
        $this->deliveryService = $deliveryService;
        $this->listsService = $listsService;
    }

    public function setShipment(Shipment $shipment): self
    {
        $this->shipment = $shipment;

        return $this;
    }

    public function documentName(): string
    {
        return 'assembling-card.docx';
    }

    protected function fillTemplate(TemplateProcessor $templateProcessor): void
    {
        $shipment = $this->shipment;
        $shipment->loadMissing('basketItems');

        $offersIds = $shipment->basketItems->pluck('offer_id')->all();
        $productsByOffers = $this->getProductsByOffers($offersIds);

        $tableRows = [];
        foreach ($shipment->basketItems as $basketItem) {
            /** @var ProductDto $product */
            $product = $productsByOffers[$basketItem->offer_id]['product'] ?? [];

            $tableRows[] = [
                'table.row' => count($tableRows) + 1,
                'table.product_article' => $product->vendor_code ?? '',
                'table.product_name' => htmlspecialchars($basketItem->name, ENT_QUOTES | ENT_XML1),
                'table.product_code_ibt' => $product->id ?? '',
                'table.product_qty' => qty_format($basketItem->qty),
                'table.product_price_per_unit' => price_format($basketItem->price / $basketItem->qty),
                'table.product_price' => price_format($basketItem->price),
            ];
        }
        $templateProcessor->cloneRowAndSetValues('table.row', $tableRows);

        $deliveryServiceId = $this->deliveryService->getZeroMileShipmentDeliveryServiceId($shipment);
        $deliveryServiceQuery = $this->listsService->newQuery()
            ->addFields(LogisticsDeliveryService::entity(), 'id', 'name');
        $deliveryService = $this->listsService->deliveryService($deliveryServiceId, $deliveryServiceQuery);

        $customerComment = $shipment->delivery->order->comment;
        $fieldValues = [
            'shipment_number' => $shipment->number,
            'delivery_service_name' => htmlspecialchars($deliveryService->name, ENT_QUOTES | ENT_XML1),
            'customer_comment' => $customerComment ? htmlspecialchars($customerComment->text, ENT_QUOTES | ENT_XML1) : 'нет',
        ];

        $templateProcessor->setValues($fieldValues);
    }

    protected function resultDocSuffix(): string
    {
        return $this->shipment->number;
    }
}
