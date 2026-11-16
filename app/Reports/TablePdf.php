<?php

namespace App\Reports;

use FPDF;

/**
 * PDF tabel laporan dengan FPDF: baris ditulis langsung ke halaman, memori tetap kecil untuk ribuan baris.
 * Font Helvetica bawaan PDF (teks dikonversi ke Windows-1252); kepala kolom diulang di setiap halaman.
 */
final class TablePdf extends FPDF
{
    private const ROW_HEIGHT = 5.5;

    /** @var list<float> */
    private array $widths = [];

    /** @var list<bool> kolom angka rata kanan */
    private array $numeric = [];

    /** @param list<string> $headings */
    public function __construct(private string $title, private string $subtitle, private array $headings, string $orientation)
    {
        parent::__construct($orientation, 'mm', 'A4');
        $this->SetMargins(10, 10);
        $this->SetAutoPageBreak(true, 12);
        $this->AliasNbPages();
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    public function render(array $rows): string
    {
        $this->layout($rows);
        $this->AddPage();
        $this->SetFont('Helvetica', '', 8);
        foreach ($rows as $index => $row) {
            $this->SetFillColor(247, 248, 250);
            foreach (array_values($row) as $column => $cell) {
                $text = is_int($cell) ? number_format($cell, 0, ',', '.') : (string) $cell;
                $this->Cell($this->widths[$column], self::ROW_HEIGHT, $this->fit($text, $this->widths[$column]), 1, 0,
                    $this->numeric[$column] ? 'R' : 'L', $index % 2 === 1);
            }
            $this->Ln();
        }

        return $this->Output('S');
    }

    public function Header(): void
    {
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(27, 95, 208);
        $this->Cell(0, 6, $this->encode($this->title), 0, 1);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(85, 85, 85);
        $this->Cell(0, 4.5, $this->encode($this->subtitle.' · dicetak '.now()->format('d/m/Y H:i')), 0, 1);
        $this->Ln(1.5);
        $this->SetTextColor(17, 17, 17);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(238, 243, 252);
        foreach ($this->headings as $column => $heading) {
            $this->Cell($this->widths[$column], self::ROW_HEIGHT + 0.5, $this->encode($heading), 1, 0, $this->numeric[$column] ? 'R' : 'L', true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 8);
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 4, 'Halaman '.$this->PageNo().' dari {nb}', 0, 0, 'R');
    }

    /** Lebar kolom sebanding panjang isi (contoh 200 baris pertama), kolom angka rata kanan. */
    private function layout(array $rows): void
    {
        $sample = array_slice($rows, 0, 200);
        $lengths = [];
        foreach ($this->headings as $column => $heading) {
            $values = array_column($sample, $column);
            $this->numeric[$column] = $values !== [] && count(array_filter($values, 'is_int')) >= count($values) / 2;
            $longest = max([mb_strlen($heading), ...array_map(fn ($value) => mb_strlen((string) $value), $values)]);
            $lengths[$column] = min(max($longest, 4), 45);
        }

        $available = $this->GetPageWidth() - 20;
        $total = array_sum($lengths);
        $this->widths = array_map(fn (int $length) => $available * $length / $total, $lengths);
    }

    private function fit(string $text, float $width): string
    {
        $text = $this->encode($text);
        while ($text !== '' && $this->GetStringWidth($text) > $width - 2) {
            $text = substr($text, 0, -1);
        }

        return $text;
    }

    private function encode(string $text): string
    {
        return (string) iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
    }
}
