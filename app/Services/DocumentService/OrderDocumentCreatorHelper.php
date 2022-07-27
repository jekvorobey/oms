<?php

namespace App\Services\DocumentService;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrderDocumentCreatorHelper
{
    /** Номер строки разрыва страницы */
    private const BREAK_TABLE_ROW = 19;

    /** Количество строк разрыва страницы */
    private const BREAK_ROWS = 4;

    /** Координаты шапки для переноса на новую страницу */
    private const BREAK_HEADER_COORDINATES = 'A12:BV14';

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

        foreach ($items as $item) {
            if ($rowIndex === self::BREAK_TABLE_ROW) {
                $rowIndex = static::fillBreakRow($sheet, $rowIndex, $breakRowTitle);
            }
            $rowValues = $getRowValues($item, $rowIndex);
            static::fillRow($sheet, $rowValues, $rowIndex);
            $rowIndex++;
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
        $sheet->mergeCells("B$breakRow:BQ$breakRow");
        $sheet->mergeCells("BR$breakRow:BT$breakRow");
        $sheet->setCellValue("B$breakRow", $breakRowTitle);
        $sheet->setCellValue("BR$breakRow", 'Лист 2');
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
            $mergeColSrcStart = Coordinate::columnIndexFromString(preg_replace('/[0-9]*/', '', $mc[0])) - 1;
            $mergeColSrcEnd = Coordinate::columnIndexFromString(preg_replace('/[0-9]*/', '', $mc[1])) - 1;
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
}
