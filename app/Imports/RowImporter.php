<?php

namespace App\Imports;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Import master per baris (FR-04, UAT-18): baris yang gagal dicatat di laporan dengan nomor barisnya
 * dan tidak menghentikan baris lain. Tiap baris disimpan dalam transaksinya sendiri (upsert per kunci).
 */
abstract class RowImporter
{
    /** @return array<string, mixed> aturan validasi Laravel; kunci = nama kolom */
    abstract protected function rules(array $row): array;

    /** Kunci upsert baris ini; kunci kembar dalam satu file dilaporkan. */
    abstract protected function rowKey(array $row): string;

    /** Simpan satu baris yang lolos validasi; lempar ImportRowException untuk penolakan aturan bisnis. */
    abstract protected function persist(array $row): void;

    /** @return list<string> */
    abstract public function columns(): array;

    public function import(string $path): ImportReport
    {
        $report = new ImportReport;
        $valid = [];
        $seen = [];

        foreach (SpreadsheetReader::rows($path) as $rowNumber => $row) {
            if ($rowNumber === 1) {
                $missing = array_diff($this->requiredColumns(), array_keys($row));
                if ($missing !== []) {
                    $report->fail(1, ['Kolom wajib tidak ada: '.implode(', ', $missing).'. Kolom yang diharapkan: '.implode(',', $this->columns()).'.']);

                    return $report;
                }

                continue;
            }

            $validator = Validator::make($row, $this->rules($row));
            if ($validator->fails()) {
                $report->fail($rowNumber, $validator->errors()->all());

                continue;
            }

            $label = $this->rowKey($row);
            $key = mb_strtolower($label);
            if (isset($seen[$key])) {
                $report->fail($rowNumber, ["{$label} sudah ada di baris {$seen[$key]} pada file ini."]);

                continue;
            }
            $seen[$key] = $rowNumber;
            $valid[$rowNumber] = $row;
        }

        $this->persistAll($valid, $report);

        return $report;
    }

    /** @param array<int, array<string, string|null>> $rows */
    protected function persistAll(array $rows, ImportReport $report): void
    {
        foreach ($rows as $rowNumber => $row) {
            $this->persistRow($rowNumber, $row, $report);
        }
    }

    protected function persistRow(int $rowNumber, array $row, ImportReport $report): void
    {
        try {
            DB::transaction(fn () => $this->persist($row));
            $report->berhasil++;
        } catch (ImportRowException $exception) {
            $report->fail($rowNumber, [$exception->getMessage()]);
        } catch (QueryException $exception) {
            report($exception);
            $report->fail($rowNumber, ['Gagal disimpan ke database (SQLSTATE '.$exception->getCode().').']);
        }
    }

    /** @return list<string> */
    private function requiredColumns(): array
    {
        $rules = $this->rules([]);

        return array_values(array_filter(
            $this->columns(),
            fn (string $column) => in_array('required', (array) ($rules[$column] ?? []), true),
        ));
    }
}
