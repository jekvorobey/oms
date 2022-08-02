<?php

namespace App\Services\DocumentService;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Helper\Dimension;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrderDocumentCreatorHelper
{
    /** Максимальный размер страницы в см */
    public const BREAK_TABLE_HEIGHT = 19;

    /** Размер (в см) блока информации покупателе и продавце */
    public const FILE_HEADER_HEIGHT = 4.1275;

    /** Размер (в см) шапки таблицы */
    public const TABLE_HEADER_HEIGHT = 2.936875;

    /** Размер (в см) блока информации после таблицы */
    public const FILE_FOOTER_HEIGHT = 9.286875;

    /** Количество строк разрыва страницы */
    public const BREAK_ROWS = 4;

    /** Координаты шапки для переноса на новую страницу */
    public const BREAK_HEADER_COORDINATES = 'A12:BV14';

    /**
     * Отображение даты в формате "10 января 2021 г."
     */
    public static function formatDate(?string $date): string
    {
        if (!$date) {
            return 'Н/У';
        }

        return Carbon::parse($date)->isoFormat('LL');
    }

    /**
     * Отображение даты в формате "10 января 2021 Г."
     */
    public static function formatDateUpper(?string $date): string
    {
        return Str::upper(static::formatDate($date));
    }

    /**
     * @throws Exception
     */
    public static function fillTableRows(
        Worksheet $sheet,
        Collection $items,
        int $startRowIndex,
        callable $getRowValues,
        array $sumColumns = [],
        string $breakRowTitle = null
    ): int {
        $rowIndex = $startRowIndex;

        if ($itemsCount = $items->count()) {
            $sheet->insertNewRowBefore($rowIndex + 1, $itemsCount);
        }

        $itemNumber = 1;
        /** Задаем размер уже заполненной информации в см */
        $pageHeight = self::FILE_HEADER_HEIGHT + self::TABLE_HEADER_HEIGHT + self::FILE_FOOTER_HEIGHT;
        $itemsHeight = 0;
        foreach ($items as $item) {
            $rowValues = $getRowValues($item, $rowIndex, $itemNumber);
            static::fillRow($sheet, $rowValues, $rowIndex);
            $itemsHeight += $sheet->getRowDimension($rowIndex)->getRowHeight(Dimension::UOM_CENTIMETERS);
            if ($itemsHeight + $pageHeight > self::BREAK_TABLE_HEIGHT) {
                $rowIndex = static::fillBreakRow($sheet, $rowIndex, $breakRowTitle);
                $pageHeight = self::TABLE_HEADER_HEIGHT + self::FILE_FOOTER_HEIGHT;
                $itemsHeight = 0;
            }
            $rowIndex++;
            $itemNumber++;
        }

        $lastRowIndex = static::getLastRowIndex($items, $sheet, $rowIndex);
        static::setTotalSumCells($sheet, $sumColumns, $startRowIndex, $lastRowIndex);

        return $lastRowIndex;
    }

    /** Заполнить данные в строке таблицы */
    public static function fillRow(Worksheet $sheet, array $values, int $rowIndex): void
    {
        foreach ($values as $columnLetter => $value) {
            $sheet->setCellValue($columnLetter . $rowIndex, $value);
            $sheet->getRowDimension($rowIndex)->setRowHeight(0.9, Dimension::UOM_CENTIMETERS);
            $sheet->getStyle($columnLetter . $rowIndex)->getAlignment()->setWrapText(true);
        }
    }

    /**
     * @throws Exception
     */
    public static function getLastRowIndex(Collection $rows, Worksheet $sheet, int $actualRowIndex): int
    {
        if ($rows) {
            // Удаляем лишнюю строку, т.к. изначально в шаблоне в таблице есть пустая строка-пример
            $sheet->removeRow($actualRowIndex);
            $lastRowIndex = $actualRowIndex - 1;
        } else {
            $lastRowIndex = $actualRowIndex;
        }

        return $lastRowIndex;
    }

    /**
     * Простановка формул суммирования данных в столбцах
     */
    public static function setTotalSumCells(
        Worksheet $sheet,
        array $columnLetters,
        int $fromRowIndex,
        int $toRowIndex
    ): void {
        $rowIndex = $toRowIndex + 1;

        foreach ($columnLetters as $columnLetter) {
            $sheet->setCellValue(
                $columnLetter . $rowIndex,
                "=SUM($columnLetter$fromRowIndex:$columnLetter$toRowIndex)"
            );
        }
    }

    /**
     * Заполнить разрыв страницы
     * @throws Exception
     */
    public static function fillBreakRow(Worksheet $sheet, int $breakRow, string $breakRowTitle): int
    {
        $sheet->insertNewRowBefore($breakRow, self::BREAK_ROWS);
        $sheet->setBreak('A' . ($breakRow - 1), Worksheet::BREAK_ROW);
        $sheet->setCellValue("B$breakRow", $breakRowTitle);
        $sheet->setCellValue("BR$breakRow", 'Лист 2');
        $sheet->duplicateStyle(new Style(), "B$breakRow:BV$breakRow");
        $sheet->getStyle("B$breakRow:BV$breakRow")->getAlignment()->setWrapText(false);
        static::copyRange($sheet, self::BREAK_HEADER_COORDINATES, 'A' . ($breakRow + 1));

        return $breakRow + self::BREAK_ROWS;
    }

    /**
     * @throws Exception
     */
    protected static function copyRange(Worksheet $sheet, $srcRange, $dstCell)
    {
        if (!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $srcRange, $srcRangeMatch)) {
            return;
        }
        if (!preg_match('/^([A-Z]+)(\d+)$/', $dstCell, $destCellMatch)) {
            return;
        }

        $srcColumnStart = $srcRangeMatch[1];
        $srcRowStart = $srcRangeMatch[2];
        $srcColumnEnd = $srcRangeMatch[3];
        $srcRowEnd = $srcRangeMatch[4];

        $destColumnStart = $destCellMatch[1];
        $destRowStart = $destCellMatch[2];

        $srcColumnStart = Coordinate::columnIndexFromString($srcColumnStart);
        $srcColumnEnd = Coordinate::columnIndexFromString($srcColumnEnd);
        $destColumnStart = Coordinate::columnIndexFromString($destColumnStart);

        foreach ($sheet->getMergeCells() as $mergeCell) {
            $mc = explode(':', $mergeCell);
            $mergeColSrcStart = Coordinate::columnIndexFromString(preg_replace('/[0-9]*/', '', $mc[0]));
            $mergeColSrcEnd = Coordinate::columnIndexFromString(preg_replace('/[0-9]*/', '', $mc[1]));
            $mergeRowSrcStart = (int) preg_replace('/[A-Z]*/', '', $mc[0]);
            $mergeRowSrcEnd = (int) preg_replace('/[A-Z]*/', '', $mc[1]);

            $relativeColStart = $mergeColSrcStart - $srcColumnStart;
            $relativeColEnd = $mergeColSrcEnd - $srcColumnStart;
            $relativeRowStart = $mergeRowSrcStart - $srcRowStart;
            $relativeRowEnd = $mergeRowSrcEnd - $srcRowStart;

            if (0 <= $mergeRowSrcStart && $mergeRowSrcStart >= $srcRowStart && $mergeRowSrcEnd <= $srcRowEnd) {
                $targetColStart = Coordinate::stringFromColumnIndex($destColumnStart + $relativeColStart);
                $targetColEnd = Coordinate::stringFromColumnIndex($destColumnStart + $relativeColEnd);
                $targetRowStart = $destRowStart + $relativeRowStart;
                $targetRowEnd = $destRowStart + $relativeRowEnd;

                $merge = $targetColStart . $targetRowStart . ':' . $targetColEnd . $targetRowEnd;

                $sheet->mergeCells($merge);
            }
        }

        $rowCount = 0;
        for ($row = $srcRowStart; $row <= $srcRowEnd; $row++) {
            $colCount = 0;
            for ($col = $srcColumnStart; $col <= $srcColumnEnd; $col++) {
                $cell = $sheet->getCellByColumnAndRow($col, $row);
                $style = $sheet->getStyleByColumnAndRow($col, $row);
                $dstCell = Coordinate::stringFromColumnIndex($destColumnStart + $colCount) . ($destRowStart + $rowCount);
                $sheet->setCellValue($dstCell, $cell->getValue());
                $sheet->duplicateStyle($style, $dstCell);

                if ($rowCount === 0) {
                    $w = $sheet->getColumnDimensionByColumn($col)->getWidth();
                    $sheet->getColumnDimensionByColumn($destColumnStart + $colCount)->setAutoSize(false);
                    $sheet->getColumnDimensionByColumn($destColumnStart + $colCount)->setWidth($w);
                }

                $colCount++;
            }

            $h = $sheet->getRowDimension($row)->getRowHeight();
            $sheet->getRowDimension($destRowStart + $rowCount)->setRowHeight($h);

            $rowCount++;
        }
    }

    /** Получить количество страниц исходя из размера строк и формата А4 */
    public static function getPageNumbers(Worksheet $sheet): float
    {
        $height = 0;
        for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $height += $sheet->getRowDimension($row)->getRowHeight(Dimension::UOM_CENTIMETERS);
        }

        return (int) ceil($height / self::BREAK_TABLE_HEIGHT);
    }
}
