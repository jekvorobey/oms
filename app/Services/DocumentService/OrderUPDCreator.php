<?php

namespace App\Services\DocumentService;

use App\Models\Basket\Basket;
use App\Models\Basket\BasketItem;
use App\Models\Delivery\Shipment;
use App\Services\OrderService;
use Cms\Services\OptionService\OptionService;
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

class OrderUPDCreator extends OrderDocumentsCreator
{
    /** Номер стартовой строки для заполнения таблицы товаров */
    private const START_BODY_TABLE_ROW = 15;

    /** Базовая ставка НДС */
    private const NDS_VALUE = 0;

    protected Shipment $shipment;

    /** Строка информации о продавце */
    protected $sellerInfo;
    /** Строка информации о покупателе */
    protected $customerInfo;

    protected Collection $offers;
    protected Collection $merchants;

    public function __construct(OrderService $orderService, OptionService $optionService)
    {
        parent::__construct($orderService, $optionService);
    }

    public function setShipment(Shipment $shipment): self
    {
        $this->shipment = $shipment;
        $this->isProductType = $shipment->delivery->order->type === Basket::TYPE_PRODUCT;

        return $this;
    }

    public function documentName(): string
    {
        return 'upd.xlsx';
    }

    public function title(): string
    {
        return "УПД № {$this->getUpdNumber()}";
    }

    public function fullTitle(): string
    {
        $today = OrderDocumentCreatorHelper::formatDate(Carbon::today());

        return "Универсальный передаточный документ № {$this->getUpdNumber()} от {$today}";
    }

    protected function getUpdNumber(): string
    {
        return $this->order ? $this->order->number : $this->shipment->number;
    }

    protected function getOfferNumber(): string
    {
        return $this->order ? $this->order->number : $this->shipment->delivery->order->number;
    }

    protected function getOfferDate(): string
    {
        return $this->order ? $this->order->created_at : $this->shipment->delivery->order->created_at;
    }

    protected function getItems()
    {
        return $this->order ? $this->order->basket->items : $this->shipment->basketItems;
    }

    protected function getPdd()
    {
        return $this->order ? $this->order->deliveries->max('pdd') : $this->shipment->delivery->pdd;
    }

    protected function getShipmentPrdInfo(): string
    {
        if ($this->order) {
            return '-- от --';
        }

        if (!$this->shipment->payment_document_number || !$this->shipment->payment_document_date) {
            return '-- от --';
        }

        return $this->shipment->payment_document_number
            . ' от '
            . Carbon::parse($this->shipment->payment_document_date)->isoFormat('L')
            . ' г.';
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
        $sheet->setTitle($this->title());
        $this->fillSellerInfo($sheet);
        $this->fillCustomerInfo($sheet);
        $this->getOffers();
        $this->getMerchants($this->offers->pluck('merchant_id')->all());
        $lastRowIndex = $this->fillBody($sheet);
        $this->fillTotalSums($sheet, $lastRowIndex);
        $this->fillFooter($sheet, $lastRowIndex);
        $this->setPageOptions($sheet);

        $writer = IOFactory::createWriter($spreadsheet, IOFactory::WRITER_XLSX);
        $path = $this->generateDocumentPath();
        $writer->save($path);

        return $path;
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

    protected function fillSellerInfo(Worksheet $sheet): void
    {
        $updDate = OrderDocumentCreatorHelper::formatDate(Carbon::now());

        /** Статус */
        $sheet->setCellValue('D5', $this->isProductType ? 1 : 2);
        /** Счет-фактура № */
        $sheet->setCellValue('P1', $this->getOfferNumber());
        /** Счет-фактура дата от */
        $sheet->setCellValue('Y1', $updDate);
        /** Продавец */
        $sheet->setCellValue('R4', $this->organizationInfo['full_name']);
        /** Адрес продавца */
        $sheet->setCellValue('R5', $this->organizationInfo['legal_address']);
        /** ИНН/КПП продавца */
        $sheet->setCellValue(
            'R6',
            $this->customer->legal_info_kpp ? $this->organizationInfo['inn'] . '/' . $this->organizationInfo['kpp'] : $this->organizationInfo['inn']
        );
        /** Грузоотправитель и его адрес */
        $sheet->setCellValue('R7', $this->organizationInfo['legal_address']);
        /** Грузополучатель и его адрес */
        $sheet->setCellValue('R8', $this->customer->legal_info_company_name . ', ' . $this->customer->legal_info_company_address);
        /** К платежно-расчетному документу № */
        $sheet->setCellValue('R9', $this->getShipmentPrdInfo());
        /** Документ об отгрузке */
        $sheet->setCellValue(
            'R10',
            '№ п/п 1-' . $this->getItems()->count() . ' № ' . $this->getOfferNumber() . ' от ' . $updDate
        );

        $this->sellerInfo = $this->organizationInfo['full_name'] . ', ИНН/КПП ' . $this->organizationInfo['inn'] . '/' . $this->organizationInfo['kpp'];
    }

    protected function fillCustomerInfo(Worksheet $sheet): void
    {
        /** Покупатель */
        $sheet->setCellValue('BF4', $this->customer->legal_info_company_name);
        /** Адрес покупателя */
        $sheet->setCellValue('BF5', $this->customer->legal_info_company_address);
        /** ИНН/КПП покупателя */
        $sheet->setCellValue('BF6', $this->customer->legal_info_inn);

        $this->customerInfo = $this->customer->legal_info_company_name;
        $this->customerInfo .= $this->customer->legal_info_kpp ?
            ', ИНН/КПП ' . $this->customer->legal_info_inn . '/' . $this->customer->legal_info_kpp : ', ИНН ' . $this->customer->legal_info_inn;
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
            $this->fullTitle()
        );
    }

    protected function fillTotalSums(Worksheet $sheet, int $lastRowIndex)
    {
        $totalSums = ['priceWithoutNds' => 0, 'ndsSum' => 0, 'price' => 0];
        /** @var BasketItem $item */
        foreach ($this->getItems() as $item) {
            if ($this->isProductType) {
                $merchantNds = $this->getMerchantVatValue($item->offer_id, $this->offers, $this->merchants);
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

    /**
     * Простановка информации о покупателе и продавце после таблицы с товарами
     */
    protected function fillFooter(Worksheet $sheet, int $toRowIndex): void
    {
        $pageNumberRowIndex = $toRowIndex + 3;
        $pageNumbers = OrderDocumentCreatorHelper::getPageNumbers($sheet);
        $listWord = trans_choice('листе|листах', $pageNumbers);
        /** Количество листов */
        $sheet->setCellValue('B' . $pageNumberRowIndex, 'Документ составлен на ' . $pageNumbers . ' ' . $listWord);
        /** Руководитель организации или иное уполномоченное лицо */
        $sheet->setCellValue('AC' . $pageNumberRowIndex, $this->getCEOInitials());
        /** Главный бухгалтер или иное уполномоченное лицо */
        $sheet->setCellValue('BO' . $pageNumberRowIndex, $this->getGeneralAccountantInitials());
        /** Основание передачи (сдачи) / получения (приемки) */
        $sheet->setCellValue(
            'T' . ($toRowIndex + 7),
            'Счёт-оферта ' . $this->getOfferNumber() . ' от ' . OrderDocumentCreatorHelper::formatDate($this->getOfferDate())
        );

        /** Инициалы гендиректора */
        $sheet->setCellValue('Z' . ($toRowIndex + 14), $this->getCEOInitials());

        /** Дата отгрузки, передачи (сдачи) */
        $sheet->setCellValue('O' . ($toRowIndex + 16), OrderDocumentCreatorHelper::formatDate($this->getPdd()));

        /** Инициалы гендиректора */
        $sheet->setCellValue('Z' . ($toRowIndex + 22), $this->getCEOInitials());

        $initialsRowIndex = $toRowIndex + 25;
        $columnLetters = ['C' => $this->sellerInfo, 'AT' => $this->customerInfo];
        foreach ($columnLetters as $column => $value) {
            $sheet->setCellValue($column . $initialsRowIndex, $value);
        }
    }

    protected function getBodyInfo(BasketItem $operation, int $operationNumber): array
    {
        if ($this->isProductType) {
            $merchantNds = $this->getMerchantVatValue($operation->offer_id, $this->offers, $this->merchants);
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

    protected function getMerchantVatValue(int $offerId, Collection $offers, Collection $merchants): int
    {
        /** @var OfferDto $offerInfo */
        $offerInfo = $offers->where('id', $offerId)->first();
        $merchant = $merchants->where('id', $offerInfo->merchant_id)->first();
        $itemMerchantVats = $merchant['vats'];
        usort($itemMerchantVats, static function ($a, $b) {
            return $b['type'] - $a['type'];
        });

        foreach ($itemMerchantVats as $vat) {
            $vatValue = $this->getVatValue($vat, $offerInfo);

            if ($vatValue !== null) {
                return $vatValue;
            }
        }

        return self::NDS_VALUE;
    }

    protected function getVatValue(array $vat, OfferDto $offerInfo): ?int
    {
        switch ($vat['type']) {
            case VatDto::TYPE_GLOBAL:
                break;
            case VatDto::TYPE_MERCHANT:
                return $vat['value'];
            case VatDto::TYPE_BRAND:
                if ($offerInfo['product']['brand_id'] === $vat['brand_id']) {
                    return $vat['value'];
                }
                break;
            case VatDto::TYPE_CATEGORY:
                if ($offerInfo['product']['category_id'] === $vat['category_id']) {
                    return $vat['value'];
                }
                break;
            case VatDto::TYPE_SKU:
                if ($offerInfo['product_id'] === $vat['product_id']) {
                    return $vat['value'];
                }
                break;
        }

        return null;
    }

    protected function resultDocSuffix(): string
    {
        return $this->order ? $this->order->number : $this->shipment->delivery->order->number;
    }
}
