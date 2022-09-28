<?php

namespace App\Services\DocumentService;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use Greensight\Customer\Dto\CustomerDto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use Pim\Core\PimException;
use Pim\Dto\BrandDto;
use Pim\Dto\CategoryDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\ProductService\ProductService;

class ShipmentsReceiptInvoiceCreator extends DocumentCreator
{
    /** Номер стартовой строки для заполнения таблицы товаров */
    private const START_BODY_TABLE_ROW = 15;
    private const BARCODE_PRODUCT_PROPERTY_ID = 297;
    protected ?Collection $shipments;
    protected ?Collection $offers;
    protected ?Collection $merchants;
    protected ?CustomerDto $customer;

    public function setShipments(?Collection $shipments): self
    {
        $this->shipments = $shipments;

        return $this;
    }

    public function documentName(): string
    {
        return 'receipt_invoice.xlsx';
    }

    public function title(): string
    {
        $today = OrderDocumentCreatorHelper::formatDate(Carbon::today());

        return "Приходная накладная (дата выгрузки: $today)";
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception|PimException
     */
    protected function createDocument(): string
    {
        $pathToTemplate = Storage::disk(self::DISK)->path($this->documentName());
        $spreadsheet = IOFactory::load($pathToTemplate);

        $sheet = $spreadsheet->getActiveSheet();
        $this->fillMerchantInfo($sheet);

        $this->getOffers();
        $this->getMerchants($this->offers->pluck('merchant_id')->all());

        $lastRowIndex = $this->fillBody($sheet);
        $this->fillTotalSums($sheet, $lastRowIndex);
        $this->setPageOptions($sheet);

        $writer = IOFactory::createWriter($spreadsheet, IOFactory::WRITER_XLSX);
        $path = $this->generateDocumentPath();
        $writer->save($path);

        return $path;
    }

    protected function fillMerchantInfo(Worksheet $sheet): void
    {
        $merchantInfo = $this->customer->legal_info_company_name;
        $merchantInfo .= $this->customer->legal_info_kpp ?
            ', ИНН/КПП ' . $this->customer->legal_info_inn . '/' . $this->customer->legal_info_kpp : ', ИНН ' . $this->customer->legal_info_inn;

        try {
            $sheet->setCellValue('B2', 'Поставщик');
            $sheet->mergeCells("C2:H2");
            $sheet->setCellValue('C2', $merchantInfo);
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        }
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function fillBody(Worksheet $sheet): int
    {
        $operations = $this->getItems();

        return OrderDocumentCreatorHelper::fillTableRows(
            $sheet,
            $operations,
            self::START_BODY_TABLE_ROW,
            function ($operation, int $rowIndex, $operationNumber) use ($sheet) {
                $sheet->mergeCells("B$rowIndex:E$rowIndex");
                $sheet->mergeCells("F$rowIndex:I$rowIndex");
                $sheet->mergeCells("J$rowIndex:R$rowIndex");
                $sheet->mergeCells("S$rowIndex:V$rowIndex");
                $sheet->mergeCells("W$rowIndex:X$rowIndex");
                $sheet->mergeCells("Y$rowIndex:Z$rowIndex");
                $sheet->mergeCells("AA$rowIndex:AC$rowIndex");
                $sheet->mergeCells("AD$rowIndex:AM$rowIndex");
                $sheet->mergeCells("AN$rowIndex:AV$rowIndex");
                $sheet->mergeCells("AW$rowIndex:AZ$rowIndex");
                $sheet->mergeCells("BA$rowIndex:BB$rowIndex");
                $sheet->mergeCells("BC$rowIndex:BF$rowIndex");
                $sheet->mergeCells("BG$rowIndex:BJ$rowIndex");
                $sheet->mergeCells("BK$rowIndex:BO$rowIndex");
                $sheet->mergeCells("BP$rowIndex:BQ$rowIndex");
                $sheet->mergeCells("BR$rowIndex:BV$rowIndex");

                return $this->getBodyInfo($operation, $operationNumber);
            },
            $this->title()
        );
    }

    protected function getBodyInfo(BasketItem $operation, int $operationNumber): array
    {
        $ndsSum = 0;
        $offer = $this->offers->where('id', $operation->offer_id)->first();

        return [
            'B' => $offer['product']['vendor_code'],
            'F' => $operationNumber,
            'J' => $operation->name,
            'S' => '--',
            'W' => '796',
            'Y' => 'шт',
            'AA' => qty_format($operation->qty),
            'AD' => ($operation->price - $ndsSum) / $operation->qty,
            'AN' => $operation->price - $ndsSum,
            'AW' => 'без акциза',
            'BA' => 'без НДС',
            'BC' => '--',
            'BG' => $operation->price,
            'BK' => '--',
            'BP' => '--',
            'BR' => '--',
        ];
    }

    public function basketItems(Shipment $shipment): array
    {
        /** @var ProductService $productService */
        $productService = resolve(ProductService::class);

        /** @var Collection|BasketItem[] $basketItems */
        $basketItems = $shipment->basketItems()->getResults()->keyBy('id');
        $products = [];
        if ($basketItems) {
            $offersIds = $basketItems->pluck('offer_id')->toArray();
            $restQuery = $productService->newQuery()
                ->addFields(ProductDto::entity(), 'vendor_code')
                ->include('properties')
                ->include(CategoryDto::entity(), BrandDto::entity());
            $productsByOffers = $productService->productsByOffers($restQuery, $offersIds);

            foreach ($basketItems as $basketItem) {
                if (!$productsByOffers->has($basketItem->offer_id)) {
                    continue;
                }

                $productByOffers = $productsByOffers[$basketItem->offer_id];
                /** @var ProductDto $product */
                $product = $productByOffers['product'];
                $products[$basketItem->id] = $product->toArray();
                $products[$basketItem->id]['barcodes'] = $this->productBarcodes($product);
                $basketItem['qty_original'] = $basketItem->qty;
            }

            if ($shipment->packages()) {
                foreach ($shipment->packages() as $package) {
                    if ($package->items()) {
                        foreach ($package->items() as $packageItem) {
                            $basketItems[$packageItem->basket_item_id]['qty'] -= $packageItem->qty;
                        }
                    }
                }
            }
        }

        return [
            'shipment' => $shipment,
            'basketItems' => $basketItems,
            'products' => $products,
        ];
    }

    private function productBarcodes(ProductDto $product): ?string
    {
        $barcodes = null;
        foreach ($product->properties as $property) {
            if ($property->property_id === self::BARCODE_PRODUCT_PROPERTY_ID && $property->value) {
                $barcodes = ($barcodes ? $barcodes . ', ' : '') . $property->value;
            }
        }

        return $barcodes;
    }

    protected function getMerchants(array $merchantIds): void
    {
        /** @var MerchantService $merchantService */
        $merchantService = resolve(MerchantService::class);
        $merchantQuery = $merchantService->newQuery()
            ->addFields(MerchantDto::entity(), 'id')
            ->include('vats')
            ->setFilter('id', $merchantIds);

        $this->merchants = $merchantService->merchants($merchantQuery)->keyBy('id');
    }

    protected function setPageOptions(Worksheet $sheet): void
    {
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.39);
        $sheet->getPageMargins()->setBottom(0.39);
    }

    protected function resultDocSuffix(): string
    {
        return date('YmdHisu');
    }
}
