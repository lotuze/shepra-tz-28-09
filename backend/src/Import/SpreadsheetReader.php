<?php

declare(strict_types=1);

namespace App\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;

final class SpreadsheetReader
{
    private const REQUIRED = ['Внешний код', 'Наименование', 'Описание', 'Цена: Цена продажи', 'Закупочная цена'];

    public function read(string $path): SpreadsheetData
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $matrix = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to read XLSX: ' . $exception->getMessage(), 0, $exception);
        }
        if ($matrix === []) {
            throw new \RuntimeException('XLSX is empty.');
        }
        $headers = array_map(static fn (mixed $value): string => trim((string) $value), array_shift($matrix));
        $missing = array_values(array_diff(self::REQUIRED, $headers));
        if ($missing !== []) {
            throw new \RuntimeException('Missing required headers: ' . implode(', ', $missing));
        }
        $rows = [];
        foreach ($matrix as $offset => $values) {
            if (count(array_filter($values, static fn (mixed $v): bool => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($headers), null);
            $rows[] = ['rowNumber' => $offset + 2, 'data' => array_combine($headers, array_slice($values, 0, count($headers)))];
        }
        return new SpreadsheetData($rows);
    }
}
