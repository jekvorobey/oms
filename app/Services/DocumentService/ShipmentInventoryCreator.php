<?php

namespace App\Services\DocumentService;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use PhpOffice\PhpWord\TemplateProcessor;
use Pim\Dto\Product\ProductDto;

class ShipmentInventoryCreator extends TemplatedDocumentCreator
{
    protected Shipment $shipment;

    public function setShipment(Shipment $shipment): self
    {
        $this->shipment = $shipment;

        return $this;
    }

    public function documentName(): string
    {
        return 'inventory.docx';
    }

    protected function fillTemplate(TemplateProcessor $templateProcessor): void
    {
        $shipment = $this->shipment;
        $shipment->loadMissing('basketItems', 'delivery');

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
                'table.product_qty' => qty_format($basketItem->qty),
                'table.product_price_per_unit' => price_format($basketItem->unit_price),
                'table.product_price' => price_format($basketItem->price),
            ];
        }
        $templateProcessor->cloneRowAndSetValues('table.row', $tableRows);

        $delivery = $shipment->delivery;
        $fieldValues = [
            'shipment_number' => $shipment->number,
            'receiver_name' => htmlspecialchars($delivery->receiver_name, ENT_QUOTES | ENT_XML1),
            'receiver_address' => $delivery->getDeliveryAddressString(),
            'table.total_product_qty' => qty_format($shipment->basketItems->sum('qty')),
            'table.total_product_price_per_unit' => $shipment->basketItems->sum(function (BasketItem $basketItem) {
                return $basketItem->price / $basketItem->qty;
            }),
            'table.total_product_price' => price_format($shipment->basketItems->sum('price')),
        ];

        $templateProcessor->setValues($fieldValues);
    }

    protected function resultDocSuffix(): string
    {
        return $this->shipment->number;
    }
}
