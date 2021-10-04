<?php

namespace App\Services\DocumentService;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\ShipmentPackageItem;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;
use Greensight\Logistics\Services\ListsService\ListsService;
use MerchantManagement\Services\MerchantService\MerchantService;
use PhpOffice\PhpWord\TemplateProcessor;
use Pim\Dto\Product\ProductDto;

class CargoAcceptanceActCreator extends TemplatedDocumentCreator
{
    protected MerchantService $merchantService;
    protected ListsService $listsService;

    protected Cargo $cargo;

    public function __construct(MerchantService $merchantService, ListsService $listsService)
    {
        $this->merchantService = $merchantService;
        $this->listsService = $listsService;
    }

    public function setCargo(Cargo $cargo): self
    {
        $this->cargo = $cargo;

        return $this;
    }

    public function documentName(): string
    {
        return 'acceptance-act.docx';
    }

    protected function fillTemplate(TemplateProcessor $templateProcessor): void
    {
        $cargo = $this->cargo;
        $cargo->loadMissing('shipments.basketItems', 'shipments.packages.items.basketItem');

        $offersIds = [];
        $totalShipmentCost = $totalShipmentPackages = $totalProductQty = $totalProductWeight = $totalProductPrice = 0;
        foreach ($cargo->shipments as $shipment) {
            $offersIds = array_merge(
                $offersIds,
                $shipment->basketItems->pluck('offer_id')->all()
            );

            $totalShipmentCost += $shipment->cost;
            $totalShipmentPackages += $shipment->packages->count();
            $totalProductQty += $shipment->basketItems->sum('qty');
            $totalProductWeight += $shipment->basketItems->sum(function (BasketItem $basketItem) {
                return isset($basketItem->product['weight']) ?
                    g2kg($basketItem->qty * $basketItem->product['weight']) : 0;
            });
            $totalProductPrice += $shipment->basketItems->sum('price');
        }
        $offersIds = array_values(array_unique($offersIds));
        $productsByOffers = $this->getProductsByOffers($offersIds);

        $tableRows = [];
        foreach ($cargo->shipments as $shipmentNum => $shipment) {
            foreach ($shipment->packages as $packageNum => $package) {
                foreach ($package->items as $itemNum => $item) {
                    /** @var ProductDto $product */
                    $product = $productsByOffers[$item->basketItem->offer_id]['product'] ?? [];
                    if ($shipment->delivery->delivery_service == LogisticsDeliveryService::SERVICE_CDEK) {
                        $package->xml_id = $shipment->delivery->xml_id;
                    }

                    $tableRows[] = [
                        'table.row' => $packageNum == 0 ? $shipmentNum + 1 : '',
                        'table.shipment_number' => $packageNum == 0 ? $shipment->number : '',
                        'table.package_barcode' => $itemNum == 0 ? $package->xml_id : '',
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
        }
        $templateProcessor->cloneRowAndSetValues('table.row', $tableRows);

        $logisticOperator = $this->listsService->deliveryService($cargo->delivery_service);
        $merchant = $this->merchantService->merchant($cargo->merchant_id);

        setlocale(LC_TIME, 'ru_RU.UTF-8');

        $tableTotalRow = [
            'table.total_shipment_cost' => price_format($totalShipmentCost),
            'table.total_shipment_packages' => $totalShipmentPackages,
            'table.total_product_qty' => qty_format($totalProductQty),
            'table.total_product_weight' => $totalProductWeight,
            'table.total_product_price' => price_format($totalProductPrice),
            'act_date' => strftime('%d %B %Y'),
            'act_id' => $cargo->id,
            'merchant_name' => htmlspecialchars($merchant->legal_name, ENT_QUOTES | ENT_XML1),
            'merchant_id' => $merchant->id,
            'merchant_register_date' => strftime('%d %B %Y', strtotime($merchant->created_at)),
            'logistic_operator_name' => htmlspecialchars($logisticOperator->legal_info_company_name ?? $logisticOperator->name, ENT_QUOTES | ENT_XML1),
        ];

        $templateProcessor->setValues($tableTotalRow);
    }

    protected function resultDocSuffix(): string
    {
        return $this->cargo->id;
    }
}
