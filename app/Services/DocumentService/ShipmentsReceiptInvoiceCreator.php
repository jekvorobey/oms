<?php

namespace App\Services\DocumentService;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Services\OrderService;
use Cms\Services\OptionService\OptionService;
use Greensight\Customer\Dto\CustomerDto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use MerchantManagement\Dto\MerchantDto;
use MerchantManagement\Dto\VatDto;
use MerchantManagement\Services\MerchantService\MerchantService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use Pim\Core\PimException;
use Pim\Dto\Offer\OfferDto;
use Pim\Services\OfferService\OfferService;

class ShipmentsReceiptInvoiceCreator extends DocumentCreator
{
    /** Номер стартовой строки для заполнения таблицы товаров */
    private const START_BODY_TABLE_ROW = 15;
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

        return "Приходная накладная (дата выгрузки: {$today})";
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

    protected function fillTotalSums(Worksheet $sheet, int $lastRowIndex)
    {
        $totalSums = ['priceWithoutNds' => 0, 'ndsSum' => 0, 'price' => 0];
        /** @var BasketItem $item */
        foreach ($this->getItems() as $item) {
            if ($this->isProductType) {
                $merchantNds = 0;
                $ndsSum = 0;
                if ($merchantNds && $merchantNds > 0) {
                    $ndsSum = -1 * ($item->price / (1 + $merchantNds / 100) - $item->price);
                }
                $totalSums['priceWithoutNds'] += $item->price - $ndsSum;
                $totalSums['ndsSum'] += $ndsSum;
                $totalSums['price'] += $item->price;
            } else {
                $totalSums['priceWithoutNds'] += $item->price;
                $totalSums['ndsSum'] = '--';
                $totalSums['price'] = $totalSums['priceWithoutNds'];
            }
        }

        $sumColumns = ['AN' => $totalSums['priceWithoutNds'], 'BC' => $totalSums['ndsSum'], 'BG' => $totalSums['price']];
        OrderDocumentCreatorHelper::setTotalSumCells($sheet, $sumColumns, $lastRowIndex);
    }

    protected function getBodyInfo(BasketItem $operation, int $operationNumber): array
    {
        if ($this->isProductType) {
            $merchantNds = 0;
            $ndsValue = 0;
            $ndsSum = 0;
            if ($merchantNds && $merchantNds > 0) {
                $ndsValue = $merchantNds;
                $ndsSum = -1 * ($operation->price / (1 + $ndsValue / 100) - $operation->price);
            }
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
                'BA' => $ndsValue ? $ndsValue . '%' : 'без НДС',
                'BC' => $ndsSum ?? '--',
                'BG' => $operation->price,
                'BK' => '--',
                'BP' => '--',
                'BR' => '--',
            ];
        }

        return [
            'F' => $operationNumber,
            'J' => $operation->name,
            'S' => '--',
            'W' => '--',
            'Y' => '--',
            'AA' => '--',
            'AD' => '--',
            'AN' => $operation->price,
            'AW' => 'без акциза',
            'BA' => '--',
            'BC' => '--',
            'BG' => $operation->price,
            'BK' => '--',
            'BP' => '--',
            'BR' => '--',
        ];
    }

    /**
     * @throws PimException
     */
    protected function getOffers(): void
    {
        $offerIds = $this->getItems()->pluck('offer_id')->all();
        /** @var OfferService $offerService */
        $offerService = resolve(OfferService::class);
        $offersQuery = $offerService->newQuery()
            ->addFields(OfferDto::entity(), 'id', 'product_id', 'merchant_id')
            ->include('product')
            ->setFilter('id', $offerIds);

        $this->offers = $offerService->offers($offersQuery)->keyBy('id');
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
