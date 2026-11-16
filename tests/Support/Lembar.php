<?php

namespace Tests\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Menulis file CSV/XLSX sementara untuk test import. */
final class Lembar
{
    /** @param list<list<string|int|float|null>> $rows */
    public static function xlsx(array $rows): string
    {
        $path = self::tempPath('xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    /** @param list<list<string|int|float|null>> $rows */
    public static function csv(array $rows, string $delimiter = ',', bool $bom = false): string
    {
        $path = self::tempPath('csv');
        $handle = fopen($path, 'w');
        if ($bom) {
            fwrite($handle, "\xEF\xBB\xBF");
        }
        foreach ($rows as $row) {
            fputcsv($handle, $row, $delimiter, '"', '');
        }
        fclose($handle);

        return $path;
    }

    private static function tempPath(string $extension): string
    {
        $directory = storage_path('framework/testing/lembar');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return $directory.'/'.uniqid('lembar-', true).'.'.$extension;
    }
}
