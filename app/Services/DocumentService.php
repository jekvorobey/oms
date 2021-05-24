<?php

namespace App\Services;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Cargo;
use App\Models\Delivery\Delivery;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackageItem;
use App\Models\Order\Order;
use App\Services\Dto\Out\DocumentDto;
use Exception;
use Greensight\CommonMsa\Services\FileService\FileService;
use Greensight\Logistics\Dto\Lists\DeliveryService as LogisticsDeliveryService;
use Greensight\Logistics\Services\ListsService\ListsService;
use Greensight\Logistics\Services\DeliveryOrderService\DeliveryOrderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use MerchantManagement\Services\MerchantService\MerchantService;
use mikehaertl\wkhtmlto\Pdf;
use PhpOffice\PhpWord\TemplateProcessor;
use Pim\Dto\Product\ProductDto;
use Pim\Services\ProductService\ProductService;

/**
 * Сервис для формирования документов
 * Class DocumentService
 * @package App\Services
 */
class DocumentService
{
    public const DISK = 'document-templates';

    /** акт-претензия для отправления */
    public const CLAIM_ACT = 'claim-act.docx';
    /** акт приема-передачи отправления/груза*/
    public const ACCEPTANCE_ACT = 'acceptance-act.docx';
    /** карточка сборки отправления */
    public const ASSEMBLING_CARD = 'assembling-card.docx';
    /** опись отправления заказа */
    public const INVENTORY = 'inventory.docx';
    /** окончание в имени файла с шаблоном для программного заполнения данными */
    public const TEMPLATE_SUFFIX = '-template';

    public const TICKETS = 'order-tickets.pdf';

    /**
     * Сформировать "Акт приема-передачи по отправлению"
     */
    public function getShipmentAcceptanceAct(Shipment $shipment): DocumentDto
    {
        $documentDto = new DocumentDto();

        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        /** @var ListsService $listsService */
        $listsService = resolve(ListsService::class);
        try {
            $templateProcessor = $this->getTemplateProcessor(self::ACCEPTANCE_ACT);
            $shipment->loadMissing('basketItems', 'packages.items.basketItem');

            $offersIds = $shipment->basketItems->pluck('offer_id')->all();
            $productsByOffers = $this->getProductsByOffers($offersIds);

            $tableRows = [];
            foreach ($shipment->packages as $packageNum => $package) {
                foreach ($package->items as $itemNum => $item) {
                    /** @var ProductDto $product */
                    $product = isset($productsByOffers[$item->basketItem->offer_id]) ?
                        $productsByOffers[$item->basketItem->offer_id]['product'] : [];
                    if ($shipment->delivery->delivery_service == LogisticsDeliveryService::SERVICE_CDEK) {
                        $package->xml_id = $shipment->delivery->xml_id;
                    }

                    // (ib-74) Если boxberry заказ и не знаем штрих код, то пытаемся его запросить здесь
                    // запросив статус заказа (в нем может быть barcode)
                    // Его изначально нет, и это происходит постоянно, потому что при создании заказа
                    // barcode = null, он появляется у apiship позднее (задержка порядка минут 5)
                    if ($shipment->delivery->delivery_service === LogisticsDeliveryService::SERVICE_BOXBERRY) {
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
                        'table.product_article' => $product ? $product->vendor_code : '',
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

            $deliveryId = Shipment::query()->where('number', '=', $shipment->number)->first()->delivery_id;
            $deliveryServiceId = Delivery::find($deliveryId)->delivery_service;
            $logisticOperator = $listsService->deliveryService($deliveryServiceId);

            $merchant = $merchantService->merchant($shipment->merchant_id);
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
                'act_id' => $shipment->id,
                'merchant_name' => htmlspecialchars($merchant->legal_name, ENT_QUOTES | ENT_XML1),
                'merchant_id' => $merchant->id,
                'merchant_register_date' => strftime('%d %B %Y', strtotime($merchant->created_at)),
                'logistic_operator_name' => htmlspecialchars($logisticOperator->legal_info_company_name ?? $logisticOperator->name, ENT_QUOTES | ENT_XML1),
            ];
            $templateProcessor->setValues($tableTotalRow);

            $documentName = $this->getFileWithSuffix(self::ACCEPTANCE_ACT, '-' . $shipment->number);
            $documentPath = Storage::disk(self::DISK)->path('') . $documentName;
            $templateProcessor->saveAs($documentPath);
            $this->saveDocument($documentDto, $documentPath, $documentName);
            Storage::disk(self::DISK)->delete($documentName);
        } catch (\Throwable $e) {
            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }

        return $documentDto;
    }

    /**
     * Сформировать "Акт приема-передачи по грузу"
     */
    public function getCargoAcceptanceAct(Cargo $cargo): DocumentDto
    {
        $documentDto = new DocumentDto();

        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        /** @var ListsService $listsService */
        $listsService = resolve(ListsService::class);

        try {
            $templateProcessor = $this->getTemplateProcessor(self::ACCEPTANCE_ACT);
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
                        $product = isset($productsByOffers[$item->basketItem->offer_id]) ?
                            $productsByOffers[$item->basketItem->offer_id]['product'] : [];
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
                            'table.product_article' => $product ? $product->vendor_code : '',
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

            $logisticOperator = $listsService->deliveryService($cargo->delivery_service);
            $merchant = $merchantService->merchant($cargo->merchant_id);
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

            $documentName = $this->getFileWithSuffix(self::ACCEPTANCE_ACT, '-' . $cargo->id);
            $documentPath = Storage::disk(self::DISK)->path('') . $documentName;
            $templateProcessor->saveAs($documentPath);
            $this->saveDocument($documentDto, $documentPath, $documentName);
            Storage::disk(self::DISK)->delete($documentName);
        } catch (\Throwable $e) {
            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }

        return $documentDto;
    }

    /**
     * Сформировать "Карточка сборки отправления"
     */
    public function getShipmentAssemblingCard(Shipment $shipment): DocumentDto
    {
        $documentDto = new DocumentDto();
        /** @var DeliveryService $deliveryService */
        $deliveryService = resolve(DeliveryService::class);

        try {
            $templateProcessor = $this->getTemplateProcessor(self::ASSEMBLING_CARD);
            $shipment->loadMissing('basketItems');

            $offersIds = $shipment->basketItems->pluck('offer_id')->all();
            $productsByOffers = $this->getProductsByOffers($offersIds);

            $tableRows = [];
            foreach ($shipment->basketItems as $basketItem) {
                /** @var ProductDto $product */
                $product = isset($productsByOffers[$basketItem->offer_id]) ?
                    $productsByOffers[$basketItem->offer_id]['product'] : [];


                $tableRows[] = [
                    'table.row' => count($tableRows) + 1,
                    'table.product_article' => $product ? $product->vendor_code : '',
                    'table.product_name' => htmlspecialchars($basketItem->name, ENT_QUOTES | ENT_XML1),
                    'table.product_code_ibt' => $product ? $product->id : '',
                    'table.product_qty' => qty_format($basketItem->qty),
                    'table.product_price_per_unit' => price_format($basketItem->price / $basketItem->qty),
                    'table.product_price' => price_format($basketItem->price),
                ];
            }
            $templateProcessor->cloneRowAndSetValues('table.row', $tableRows);

            $deliveryServiceId = $deliveryService->getZeroMileShipmentDeliveryServiceId($shipment);
            /** @var ListsService $listsService */
            $listsService = resolve(ListsService::class);
            $deliveryServiceQuery = $listsService->newQuery()
                ->addFields(LogisticsDeliveryService::entity(), 'id', 'name');
            $deliveryService = $listsService->deliveryService($deliveryServiceId, $deliveryServiceQuery);

            $customerComment = $shipment->delivery->order->comment;
            $fieldValues = [
                'shipment_number' => $shipment->number,
                'delivery_service_name' => htmlspecialchars($deliveryService->name, ENT_QUOTES | ENT_XML1),
                'customer_comment' => $customerComment ? htmlspecialchars($customerComment->text, ENT_QUOTES | ENT_XML1) : 'нет',
            ];
            $templateProcessor->setValues($fieldValues);

            $documentName = $this->getFileWithSuffix(self::ASSEMBLING_CARD, '-' . $shipment->number);
            $documentPath = Storage::disk(self::DISK)->path('') . $documentName;
            $templateProcessor->saveAs($documentPath);
            $this->saveDocument($documentDto, $documentPath, $documentName);
            Storage::disk(self::DISK)->delete($documentName);
        } catch (\Throwable $e) {
            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }

        return $documentDto;
    }

    /**
     * Сформировать "Опись отправления заказа"
     */
    public function getShipmentInventory(Shipment $shipment): DocumentDto
    {
        $documentDto = new DocumentDto();
        /** @var DeliveryService $deliveryService */
        $deliveryService = resolve(DeliveryService::class);

        try {
            $templateProcessor = $this->getTemplateProcessor(self::INVENTORY);
            $shipment->loadMissing('basketItems', 'delivery');

            $offersIds = $shipment->basketItems->pluck('offer_id')->all();
            $productsByOffers = $this->getProductsByOffers($offersIds);

            $tableRows = [];
            foreach ($shipment->basketItems as $basketItem) {
                /** @var ProductDto $product */
                $product = isset($productsByOffers[$basketItem->offer_id]) ?
                    $productsByOffers[$basketItem->offer_id]['product'] : [];


                $tableRows[] = [
                    'table.row' => count($tableRows) + 1,
                    'table.product_article' => $product ? $product->vendor_code : '',
                    'table.product_name' => htmlspecialchars($basketItem->name, ENT_QUOTES | ENT_XML1),
                    'table.product_qty' => qty_format($basketItem->qty),
                    'table.product_price_per_unit' => price_format($basketItem->price / $basketItem->qty),
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

            $documentName = $this->getFileWithSuffix(self::INVENTORY, '-' . $shipment->number);
            $documentPath = Storage::disk(self::DISK)->path('') . $documentName;
            $templateProcessor->saveAs($documentPath);
            $this->saveDocument($documentDto, $documentPath, $documentName);
            Storage::disk(self::DISK)->delete($documentName);
        } catch (\Throwable $e) {
            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }

        return $documentDto;
    }

    /**
     * @param int|null $basketItemId - id элемента корзины, для которого необходимо получить pdf-файл с билетами
     * @throws \Throwable
     */
    public function getOrderPdfTickets(Order $order, ?int $basketItemId = null): DocumentDto
    {
        if (!$order->isPaid()) {
            throw new Exception('Order is not paid');
        }

        $documentDto = new DocumentDto();
        try {
            $pdf = new Pdf();

            /** @var OrderService $orderService */
            $orderService = resolve(OrderService::class);
            $orderInfoDto = $orderService->getPublicEventsOrderInfo($order, true, $basketItemId);
            if (!$orderInfoDto) {
                throw new Exception('Order is not PublicEventOrder');
            }

            $html = view('pdf::ticket', [
                'order' => $orderInfoDto,
            ])->render();
            $pdf->addPage($html, [], Pdf::TYPE_HTML);
            $documentName = $this->getFileWithSuffix(self::TICKETS, '-' . $order->number);
            $documentPath = Storage::disk(self::DISK)->path('') . $documentName;
            if (Storage::disk(self::DISK)->put($documentName, '')) {
                $pdf->saveAs($documentPath);
                $this->saveDocument($documentDto, $documentPath, $documentName);
                Storage::disk(self::DISK)->delete($documentName);
            }
        } catch (\Throwable $e) {
            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }

        return $documentDto;
    }

    /**
     * Получить название файла с шаблоном для программного заполнения данными.
     * Например, для claim-act.docx будет claim-act-template.docx
     */
    protected function getFileWithSuffix(string $template, string $suffix): string
    {
        $pathParts = pathinfo($template);

        return $pathParts['filename'] . $suffix . '.' . $pathParts['extension'];
    }

    /**
     * @throws \PhpOffice\PhpWord\Exception\CopyFileException
     * @throws \PhpOffice\PhpWord\Exception\CreateTemporaryFileException
     */
    protected function getTemplateProcessor(string $template): TemplateProcessor
    {
        $programTemplate = $this->getFileWithSuffix($template, self::TEMPLATE_SUFFIX);

        return new TemplateProcessor(Storage::disk(self::DISK)->path($programTemplate));
    }

    /**
     * Получить товары по офферам
     * @param array $offersIds
     */
    protected function getProductsByOffers(array $offersIds): Collection
    {
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);
        $productQuery = $productService->newQuery()
            ->addFields(ProductDto::entity(), 'id', 'vendor_code');

        return $productService->productsByOffers($productQuery, $offersIds);
    }

    /**
     * Сохранить сформированный документ в сервис file
     * @param string $documentPath - полный путь к документу на сервере oms
     * @param string $documentName - название файла документа
     */
    protected function saveDocument(DocumentDto $documentDto, string $documentPath, string $documentName)
    {
        try {
            /** @var FileService $fileService */
            $fileService = resolve(FileService::class);
            $fileId = $fileService->uploadFile('oms-documents', $documentName, $documentPath);
            if ($fileId) {
                $documentDto->success = true;
                $documentDto->file_id = $fileId;
            } else {
                $documentDto->success = false;
                $documentDto->message = 'Ошибка при сохранении документа';
            }
        } catch (\Throwable $e) {
            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }
    }
}
