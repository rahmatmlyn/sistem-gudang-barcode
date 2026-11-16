<?php

namespace App\Imports;

use DateTimeInterface;
use Generator;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Membaca lembar pertama file XLSX atau CSV (koma/titik koma, dengan/tanpa BOM) sebagai baris asosiatif.
 * Format dikenali dari isi file, bukan ekstensi (nama file unggahan bisa berubah).
 */
final class SpreadsheetReader
{
    /**
     * @return Generator<int, array<string, string|null>> nomor baris lembar → [header => nilai]; baris 1 = header
     */
    public static function rows(string $path): Generator
    {
        $reader = self::readerFor($path);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $values = array_map(self::normalize(...), $row->toArray());
                    if ($headers === null) {
                        $headers = array_map(fn (?string $header) => strtolower(trim((string) $header, " \t\n\r\0\x0B\u{FEFF}")), $values);
                        yield 1 => array_combine($headers, $headers);

                        continue;
                    }
                    if (array_filter($values, fn (?string $value) => $value !== null) === []) {
                        continue;
                    }
                    $values = array_pad(array_slice($values, 0, count($headers)), count($headers), null);
                    yield $rowNumber => array_combine($headers, $values);
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    private static function readerFor(string $path): ReaderInterface
    {
        $head = (string) file_get_contents($path, length: 4096);

        if (str_starts_with($head, "PK\x03\x04")) {
            $options = new XlsxOptions;
            $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

            return new XlsxReader($options);
        }

        $firstLine = strtok($head, "\n") ?: '';
        $options = new CsvOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        return new CsvReader($options);
    }

    private static function normalize(mixed $value): ?string
    {
        $text = match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) && floor($value) === $value && abs($value) < 1e15 => sprintf('%.0f', $value),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
