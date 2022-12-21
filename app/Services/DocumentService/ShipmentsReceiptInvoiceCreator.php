<?php

namespace App\Services\DocumentService;

use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use NcJoes\OfficeConverter\OfficeConverter;
use NcJoes\OfficeConverter\OfficeConverterException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use Pim\Core\PimException;
use Pim\Dto\BrandDto;
use Pim\Dto\CategoryDto;
use Pim\Dto\Offer\OfferDto;
use Pim\Dto\Product\ProductDto;
use Pim\Services\ProductService\ProductService;
use RuntimeException;

class ShipmentsReceiptInvoiceCreator extends DocumentCreator
{
    /** Номер стартовой строки для заполнения таблицы товаров */
    private const START_BODY_TABLE_ROW = 4;
    private const BARCODE_PRODUCT_PROPERTY_ID = 297;

    protected bool $asPdf = false;
    protected float $totalQty = 0;

    protected ?MerchantService $merchantService;
    protected ?Collection $shipments;
    protected ?Collection $offers;
    protected ?MerchantDto $merchant;

    public function __construct(MerchantService $merchantService)
    {
        $this->merchantService = $merchantService;
    }

    public function documentName(): string
    {
        return 'receipt_invoice.xlsx';
    }

    public function setShipments(?Collection $shipments): self
    {
        $this->shipments = $shipments;
        $this->setMerchant();

        return $this;
    }

    public function setMerchant(): void
    {
        /** @var Shipment $shipment */
        foreach ($this->shipments as $shipment) {
            $merchant = $this->merchantService->merchant($shipment->merchant_id);
            if ($merchant->id) {
                $this->merchant = $merchant;

                break;
            }
        }
    }

    public function setAsPdf(bool $asPdf): self
    {
        $this->asPdf = $asPdf;

        return $this;
    }

    public function title(): string
    {
        $today = OrderDocumentCreatorHelper::formatDate(Carbon::today());

        return "Приходная накладная (дата выгрузки: $today)";
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception|PimException
     * @throws OfficeConverterException
     */
    protected function createDocument(): string
    {
        $pathToTemplate = Storage::disk(self::DISK)->path($this->documentName());
        $spreadsheet = IOFactory::load($pathToTemplate);

        $sheet = $spreadsheet->getActiveSheet();
        $this->fillTitle($sheet);
        $this->fillMerchantInfo($sheet);
        $lastRowIndex = $this->fillBody($sheet);
        $this->fillTotalQty($sheet, $lastRowIndex);
        $this->setPageOptions($sheet);

        $writer = IOFactory::createWriter($spreadsheet, IOFactory::WRITER_XLSX);
        $path = $this->generateDocumentPath();
        $writer->save($path);

        if ($this->asPdf) {
            $path = $this->convertToPdf($path);
        }

        return $path;
    }

    protected function fillTitle(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', $this->title());
    }

    protected function fillMerchantInfo(Worksheet $sheet): void
    {
        if ($this->merchant) {
            $merchantInfo = $this->merchant->legal_name;
            $merchantInfo .= $this->merchant->kpp ?
                ', ИНН/КПП ' . $this->merchant->inn . '/' . $this->merchant->kpp : ', ИНН ' . $this->merchant->inn;
        } else {
            $merchantInfo = '';
        }

        $sheet->setCellValue('C2', $merchantInfo);
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
            function ($operation, int $rowIndex, $operationNumber) {
                return $this->getBodyInfo($operation, $operationNumber);
            }
        );
    }

    protected function fillTotalQty(Worksheet $sheet, int $lastRowIndex): int
    {
        ++$lastRowIndex;
        $sheet->setCellValue("F$lastRowIndex", $this->totalQty);

        return $lastRowIndex;
    }

    protected function getBodyInfo(array $operation, int $operationNumber): array
    {
        return [
            'A' => $operationNumber,
            'B' => $operation['vendor_code'] ?? '',
            'C' => $operation['barcodes'] ?? '',
            'D' => $operation['xml_id'] ?? '',
            'E' => $operation['name'] ?? '',
            'F' => $operation['qty'] ?? '',
            'G' => $operation['shipments_number'] ?? '',
        ];
    }

    protected function getItems(): Collection
    {
        $products = [];

        /** @var Shipment $shipment */
        foreach ($this->shipments as $shipment) {
            $basketItems = $this->basketItems($shipment);

            foreach ($basketItems['products'] as $basketItemId => $product) {
                $item = $product;
                $item['name'] = $basketItems['basketItems'][$basketItemId]['name'] ?? '';
                $item['qty'] = $basketItems['basketItems'][$basketItemId]['qty'] ?? 0;
                $item['shipments_number'] = $shipment->number;

                if (isset($products[$product['id']])) {
                    $products[$product['id']]['qty'] += $item['qty'];
                    $products[$product['id']]['shipments_number'] .= ', ' . $item['shipments_number'];
                } else {
                    $products[$product['id']] = $item;
                }
            }
        }

        $itemsCollection = new Collection();
        foreach ($products as $product) {
            $this->totalQty += $product['qty'];
            $itemsCollection->add($product);
        }

        return $itemsCollection;
    }

    protected function basketItems(Shipment $shipment): array
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
                /** @var OfferDto $offer */
                $offer = $productByOffers['offer'];

                $productArray = $product->toArray();
                $productArray['xml_id'] = $offer->xml_id; //$offer->merchant_id . '/' . $offer->product_id . '/' . $offer->id;

                $products[$basketItem->id] = $productArray;
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
            'basketItems' => $basketItems,
            'products' => $products,
        ];
    }

    protected function productBarcodes(ProductDto $product): ?string
    {
        $barcodes = null;
        foreach ($product->properties as $property) {
            if ($property->property_id === self::BARCODE_PRODUCT_PROPERTY_ID && $property->value) {
                $barcodes = ($barcodes ? $barcodes . ', ' : '') . $property->value;
            }
        }

        return $barcodes;
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
        $suffix = '';

        /** @var Shipment $shipment */
        foreach ($this->shipments->all() as $shipment) {
            $suffix = ($suffix ? $suffix . '-' : '') . $shipment->id;
        }

        return md5($suffix);
    }

    /**
     * @throws OfficeConverterException
     */
    protected function convertToPdf(string $path): string
    {
        if (!$bin = config('libreoffice.bin')) {
            throw new RuntimeException('libreoffice.bin is empty!');
        }

        $converter = new OfficeConverter($path, null, $bin, false);

        $filename = pathinfo($path, PATHINFO_FILENAME);

        $pdfPath = $converter->convertTo("$filename.pdf");

        unlink($path);

        return $pdfPath;
    }
}
