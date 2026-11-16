<?php

namespace App\Imports;

/** Hasil satu import: jumlah baris masuk + pesan per nomor baris lembar (baris 1 = header). */
final class ImportReport
{
    public int $berhasil = 0;

    /** @var array<int, list<string>> */
    public array $gagal = [];

    /** @param list<string> $messages */
    public function fail(int $rowNumber, array $messages): void
    {
        $this->gagal[$rowNumber] = [...($this->gagal[$rowNumber] ?? []), ...$messages];
        ksort($this->gagal);
    }

    public function jumlahGagal(): int
    {
        return count($this->gagal);
    }

    public function ringkasan(): string
    {
        return "{$this->berhasil} baris masuk, {$this->jumlahGagal()} baris gagal.";
    }

    public function toCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['baris', 'pesan'], ',', '"', '');
        foreach ($this->gagal as $rowNumber => $messages) {
            fputcsv($handle, [$rowNumber, implode('; ', $messages)], ',', '"', '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
