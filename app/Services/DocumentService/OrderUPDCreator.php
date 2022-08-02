<?php

namespace App\Services\DocumentService;

use App\Services\OrderService;
use Cms\Core\CmsException;
use Cms\Services\OptionService\OptionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;

class OrderUPDCreator extends OrderDocumentsCreator
{
    /** Номер стартовой строки для заполнения таблицы товаров */
    private const START_BODY_TABLE_ROW = 15;

    /** Строка информации о продавце */
    protected $sellerInfo;
    /** Строка информации о покупателе */
    protected $customerInfo;

    public function __construct(OrderService $orderService, OptionService $optionService)
    {
        parent::__construct($orderService, $optionService);
    }

    public function documentName(): string
    {
        return 'upd.xlsx';
    }

    public function title(): string
    {
        return "УПД № {$this->order->number}";
    }

    public function fullTitle(): string
    {
        $today = OrderDocumentCreatorHelper::formatDate(Carbon::today());

        return "Универсальный передаточный документ № {$this->order->number} от {$today}";
    }

    /**
     * @throws Exception
     * @throws CmsException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function createDocument(): string
    {
        $pathToTemplate = Storage::disk(self::DISK)->path($this->documentName());
        $spreadsheet = IOFactory::load($pathToTemplate);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->title());
        $this->fillSellerInfo($sheet);
        $this->fillCustomerInfo($sheet);
        $lastRowIndex = $this->fillBody($sheet);
        $this->fillFooter($sheet, $lastRowIndex);
        $this->setPageOptions($sheet);

        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
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
    }

    /**
     * @throws CmsException
     */
    protected function fillSellerInfo(Worksheet $sheet): void
    {
        $contractDate = OrderDocumentCreatorHelper::formatDate($this->order->created_at);
        $organizationInfo = $this->getOrganizationInfo();

        $sheet->setCellValue('P1', $this->order->number);
        $sheet->setCellValue('Y1', $contractDate);
        $sheet->setCellValue('R4', $organizationInfo['full_name']);
        $sheet->setCellValue('R5', $organizationInfo['legal_address']);
        $sheet->setCellValue('R4', $organizationInfo['inn'] . '/' . $organizationInfo['kpp']);
        $sheet->setCellValue('R10', '№ п/п 1-5 №' . $this->order->number . ' от ' . Carbon::today()->format('d.m.Y'));

        $this->sellerInfo = $organizationInfo['full_name'] . ', ИНН/КПП ' . $organizationInfo['inn'] . '/' . $organizationInfo['kpp'];
    }

    protected function fillCustomerInfo(Worksheet $sheet): void
    {
        $sheet->setCellValue('BF4', $this->customer->legal_info_company_name);
        $sheet->setCellValue('BF5', $this->customer->legal_info_company_address);
        $sheet->setCellValue('BF6', $this->customer->legal_info_inn);

        $this->customerInfo = $this->customer->legal_info_company_name . ', ИНН ' . $this->customer->legal_info_inn;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function fillBody(Worksheet $sheet): int
    {
        $operations = $this->order->basket->items;

        return OrderDocumentCreatorHelper::fillTableRows(
            $sheet,
            $operations,
            self::START_BODY_TABLE_ROW,
            function ($operation, int $rowIndex, $operationNumber) use ($sheet) {
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
            },
            ['AN', 'BG'],
            $this->fullTitle()
        );
    }

    /**
     * Простановка информации о покупателе и продавце после таблицы с товарами
     */
    protected function fillFooter(Worksheet $sheet, int $toRowIndex): void
    {
        $pageNumberRowIndex = $toRowIndex + 3;
        $pageNumbers = OrderDocumentCreatorHelper::getPageNumbers($sheet);
        $listWord = trans_choice('листе|листах', $pageNumbers);
        $sheet->setCellValue('B' . $pageNumberRowIndex, 'Документ составлен на ' . $pageNumbers . ' ' . $listWord);

        $initialsRowIndex = $toRowIndex + 25;
        $columnLetters = ['C' => $this->sellerInfo, 'AT' => $this->customerInfo];
        foreach ($columnLetters as $column => $value) {
            $sheet->setCellValue($column . $initialsRowIndex, $value);
        }
    }
}
