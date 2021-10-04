<?php

namespace App\Services\DocumentService;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackageItem;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;
use Greensight\Logistics\Services\ListsService\ListsService;
use MerchantManagement\Services\MerchantService\MerchantService;
use PhpOffice\PhpWord\TemplateProcessor;
use Pim\Dto\Product\ProductDto;

class ShipmentAcceptanceActCreator extends TemplatedDocumentCreator
{
    protected MerchantService $merchantService;
    protected ListsService $listsService;

    protected Shipment $shipment;

    public function __construct(MerchantService $merchantService, ListsService $listsService)
    {
        $this->merchantService = $merchantService;
        $this->listsService = $listsService;
    }

    public function setShipment(Shipment $shipment): self
    {
        $this->shipment = $shipment;

        return $this;
    }

    public function documentName(): string
    {
        return 'acceptance-act.docx';
    }

    protected function fillTemplate(TemplateProcessor $templateProcessor): void
    {
        $shipment = $this->shipment;
        $shipment->loadMissing('basketItems', 'packages.items.basketItem');

        $offersIds = $shipment->basketItems->pluck('offer_id')->all();
        $productsByOffers = $this->getProductsByOffers($offersIds);

        $tableRows = [];
        foreach ($shipment->packages as $packageNum => $package) {
            foreach ($package->items as $itemNum => $item) {
                /** @var ProductDto $product */
                $product = $productsByOffers[$item->basketItem->offer_id]['product'] ?? [];
                if ($shipment->delivery->delivery_service == LogisticsDeliveryService::SERVICE_CDEK) {
                    $package->xml_id = $shipment->delivery->xml_id;
                } elseif ($shipment->delivery->delivery_service === LogisticsDeliveryService::SERVICE_BOXBERRY) {
                    // (ib-74) Если boxberry заказ и не знаем штрих код, то пытаемся его запросить здесь
                    // запросив статус заказа (в нем может быть barcode)
                    // Его изначально нет, и это происходит постоянно, потому что при создании заказа
                    // barcode = null, он появляется у apiship позднее (задержка порядка минут 5)
                    if ($shipment->delivery->xml_id && !$shipment->delivery->barcode) {
                        // делаем это в транзакции - что бы не поломать остальной код
                        try {
                            $status = resolve(DeliveryOrderService::class)
                                ->statusOrders(LogisticsDeliveryService::SERVICE_BOXBERRY, [$shipment->delivery->xml_id])
                                ->first();
                            $isChange = false;
                            if (!$shipment->delivery->barcode && $status->barcode) {
                                $shipment->delivery->barcode = $status->barcode;
                                $isChange = true;
                            }
                            if (!$shipment->delivery->tracknumber && $status->tracknumber) {
                                $shipment->delivery->tracknumber = $status->tracknumber;
                                $isChange = true;
                            }
                            if ($isChange) {
                                $shipment->delivery->save();
                            }
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                }

                $tableRows[] = [
                    'table.row' => $packageNum == 0 ? 1 : '',
                    'table.shipment_number' => $packageNum == 0 ? $shipment->number : '',
                    'table.package_barcode' => $itemNum == 0 ? ($package->xml_id ?? $shipment->delivery->barcode) : '',
                    'table.shipment_cost' => $itemNum == 0 ? price_format(
                        $package->items->sum(function (ShipmentPackageItem $item) {
                            return $item->qty * $item->basketItem->price / $item->basketItem->qty;
                        })
                    ) : '',
                    'table.shipment_packages' => ($packageNum + 1) . '/' . $shipment->packages->count(),
                    'table.product_article' => $product->vendor_code ?? '',
                    'table.product_name' => htmlspecialchars($item->basketItem->name, ENT_QUOTES | ENT_XML1),
                    'table.product_qty' => qty_format($item->qty),
                    'table.product_weight' => isset($item->basketItem->product['weight']) ?
                        g2kg($item->qty * $item->basketItem->product['weight']) : '',
                    'table.product_price' => price_format(
                        $item->qty * $item->basketItem->price / $item->basketItem->qty
                    ),
                ];
            }
        }

        $templateProcessor->cloneRowAndSetValues('table.row', $tableRows);

        $logisticOperator = $this->listsService->deliveryService($shipment->delivery->delivery_service);
        $merchant = $this->merchantService->merchant($shipment->merchant_id);

        setlocale(LC_TIME, 'ru_RU.UTF-8');

        $tableTotalRow = [
            'table.total_shipment_cost' => price_format($shipment->cost),
            'table.total_shipment_packages' => $shipment->packages->count(),
            'table.total_product_qty' => qty_format($shipment->basketItems->sum('qty')),
            'table.total_product_weight' => $shipment->basketItems->sum(function (BasketItem $basketItem) {
                return isset($basketItem->product['weight']) ?
                    g2kg($basketItem->qty * $basketItem->product['weight']) : 0;
            }),
            'table.total_product_price' => price_format($shipment->basketItems->sum('price')),
            'act_date' => strftime('%d %B %Y'),
            'act_id' => $shipment->cargo_id ?: $shipment->id,
            'merchant_name' => htmlspecialchars($merchant->legal_name, ENT_QUOTES | ENT_XML1),
            'merchant_id' => $merchant->id,
            'merchant_register_date' => strftime('%d %B %Y', strtotime($merchant->created_at)),
            'logistic_operator_name' => htmlspecialchars($logisticOperator->legal_info_company_name ?? $logisticOperator->name, ENT_QUOTES | ENT_XML1),
        ];

        $templateProcessor->setValues($tableTotalRow);
    }

    protected function resultDocSuffix(): string
    {
        return $this->shipment->number;
    }
}
