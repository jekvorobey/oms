<?php

namespace App\Services\DocumentService;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrderDocumentCreatorHelper
{
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
        array $footerColumns = []
    ): int {
        $rowIndex = $startRowIndex;

        if ($itemsCount = $items->count()) {
            $sheet->insertNewRowBefore($rowIndex + 1, $itemsCount);
        }

        foreach ($items as $item) {
            $rowValues = $getRowValues($item, $rowIndex);
            static::fillRow($sheet, $rowValues, $rowIndex);
            $rowIndex++;
        }

        $lastRowIndex = static::getLastRowIndex($items, $sheet, $rowIndex);
        static::setTotalSumCells($sheet, $sumColumns, $startRowIndex, $lastRowIndex);
        static::setFooterCells($sheet, $footerColumns, $lastRowIndex);

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
     * Простановка информации о покупателе и продавце после таблицы с товарами
     */
    public static function setFooterCells(Worksheet $sheet, array $columnLetters, int $toRowIndex): void
    {
        $rowIndex = $toRowIndex + 25;

        foreach ($columnLetters as $column => $value) {
            $sheet->setCellValue($column . $rowIndex, $value);
        }
    }
}
