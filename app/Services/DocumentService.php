<?php

namespace App\Services;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Models\Delivery\ShipmentPackageItem;
use App\Services\Dto\Out\DocumentDto;
use Exception;
use Greensight\CommonMsa\Services\FileService\FileService;
use Illuminate\Support\Facades\Storage;
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
    /** @var string - акт-претензия для отправления */
    public const CLAIM_ACT = 'claim-act.docx';
    /** @var string - акт приема-передачи отправления/груза*/
    public const ACCEPTANCE_ACT = 'acceptance-act.docx';
    /** @var string - карточка сборки отправления */
    public const ASSEMBLING_CARD = 'assembling-card.docx';
    /** @var string - опись отправления заказа */
    public const INVENTORY = 'inventory.docx';
    /** @var string - окончание в имени файла с шаблоном для программного заполнения данными */
    public const TEMPLATE_SUFFIX = '-template';

    /**
     * Сформировать "Акт приема-передачи по отправлению"
     * @param  Shipment $shipment
     * @return DocumentDto
     */
    public function getShipmentAcceptanceAct(Shipment $shipment): DocumentDto
    {
        $documentDto = new DocumentDto();

        try {
            $templateProcessor = $this->getTemplateProcessor(self::ACCEPTANCE_ACT);
            $shipment->loadMissing('basketItems', 'packages.items.basketItem');

            $offersIds = $shipment->basketItems->pluck('offer_id')->all();
            /** @var ProductService $productService */
            $productService = resolve(ProductService::class);
            $productQuery = $productService->newQuery()
                ->addFields(ProductDto::entity(), 'vendor_code');
            $productsByOffers = $productService->productsByOffers($productQuery, $offersIds);

            $tableRows = [];
            foreach ($shipment->packages as $packageNum => $package) {
                foreach ($package->items as $itemNum => $item) {
                    $product = isset($productsByOffers[$item->basketItem->offer_id]) ?
                        $productsByOffers[$item->basketItem->offer_id]['product'] : [];
                    $tableRows[] = [
                        'table.row_number' => $itemNum == 0 ? count($tableRows) + 1 : '',
                        'table.shipment_number' => $itemNum == 0 ? $shipment->number : '',
                        'table.package_barcode' => $itemNum == 0 ? $package->xml_id : '',
                        'table.shipment_cost' => $itemNum == 0 ? price_format($package->items->sum(function (ShipmentPackageItem $shipmentPackageItem) {
                            return $shipmentPackageItem->basketItem->price;
                        })) : '',
                        'table.shipment_packages' => ($packageNum + 1) . '/' .$shipment->packages->count(),
                        'table.product_article' => $product ? $product['vendor_code'] : '',
                        'table.product_name' => $item->basketItem->name,
                        'table.product_qty' => qty_format($item->basketItem->qty),
                        'table.product_weight' => isset($item->basketItem->product['weight']) ?
                            g2kg($item->basketItem->product['weight']) : '',
                        'table.product_price' => price_format($item->basketItem->price),
                    ];
                }
            }
            $templateProcessor->cloneRowAndSetValues('table.row_number', $tableRows);

            $tableTotalRow = [
                'table.total_shipment_cost' => price_format($shipment->cost),
                'table.total_shipment_packages' => $shipment->packages->count(),
                'table.total_product_qty' => qty_format($shipment->basketItems->sum('qty')),
                'table.total_product_weight' => $shipment->basketItems->sum(function (BasketItem $basketItem) {
                    return isset($basketItem->product['weight']) ?
                        g2kg($basketItem->product['weight']) : 0;
                }),
                'table.total_product_price' => price_format($shipment->basketItems->sum('price')),
            ];
            $templateProcessor->setValues($tableTotalRow);

            $documentName = $this->getFileWithSuffix(self::ACCEPTANCE_ACT, '-' . $shipment->number);
            $documentPath = Storage::disk('document-templates')->path('') . $documentName;
            $templateProcessor->saveAs($documentPath);
            $this->saveDocument($documentDto, $documentPath, $documentName);
            Storage::disk('document-templates')->delete($documentName);
        } catch (Exception $e) {
            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }

        return $documentDto;
    }

    /**
     * Получить название файла с шаблоном для программного заполнения данными.
     * Например, для claim-act.docx будет claim-act-template.docx
     * @param  string  $template
     * @return string
     */
    protected function getFileWithSuffix(string $template, string $suffix): string
    {
        $pathParts = pathinfo($template);

        return $pathParts['filename'] . $suffix . '.' . $pathParts['extension'];
    }

    /**
     * @param  string  $template
     * @return TemplateProcessor
     * @throws \PhpOffice\PhpWord\Exception\CopyFileException
     * @throws \PhpOffice\PhpWord\Exception\CreateTemporaryFileException
     */
    protected function getTemplateProcessor(string $template): TemplateProcessor
    {
        $programTemplate = $this->getFileWithSuffix($template, self::TEMPLATE_SUFFIX);

        return new TemplateProcessor(Storage::disk('document-templates')->path($programTemplate));
    }

    /**
     * Сохранить сформированный документ в сервис file
     * @param  DocumentDto  $documentDto
     * @param  string  $documentPath - полный путь к документу на сервере oms
     * @param  string  $documentName - название файла документа
     */
    protected function saveDocument(DocumentDto $documentDto, string $documentPath, string $documentName)
    {
        try {
            /** @var FileService $fileService */
            $fileService = resolve(FileService::class);
            $fileId = $fileService->uploadFile(
                'oms-documents',
                $documentName,
                $documentPath
            );
            if ($fileId) {
                $documentDto->success = true;
                $documentDto->file_id = $fileId;
            } else {
                $documentDto->success = false;
                $documentDto->message = 'Ошибка при сохранении документа';
            }
        } catch (Exception $e) {
            $documentDto->success = false;
            $documentDto->message = $e->getMessage();
        }
    }
}
