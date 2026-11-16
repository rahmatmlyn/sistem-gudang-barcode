<?php

namespace App\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Export laporan ke Excel (openspout) dan PDF (dompdf); keduanya mengembalikan isi file. */
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
        return Pdf::loadView('pdf.laporan', compact('title', 'subtitle', 'headings', 'rows'))
            ->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait')
            ->output();
    }
}
