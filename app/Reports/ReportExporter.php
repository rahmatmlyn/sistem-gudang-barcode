<?php

namespace App\Reports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Export laporan ke Excel (openspout) dan PDF (FPDF, TablePdf); keduanya mengembalikan isi file. */
final class ReportExporter
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int>>  $rows
     */
    public static function xlsx(array $headings, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'laporan').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headings));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int>>  $rows
     */
    public static function pdf(string $title, string $subtitle, array $headings, array $rows): string
    {
        return (new TablePdf($title, $subtitle, $headings, count($headings) > 6 ? 'L' : 'P'))->render($rows);
    }
}
