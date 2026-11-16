<?php

namespace App\Imports;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;
use App\Rules\ParentLokasiValid;
use App\Support\BarcodeRegistry;
use Closure;

/**
 * Kolom sama dengan database/dummy/locations.csv; upsert per (gudang, kode).
 * Kolom parent berisi kode lokasi induk, atau kode gudang/kosong untuk lokasi teratas.
 * Urutan baris bebas: baris yang induknya muncul belakangan disimpan setelah induknya.
 */
class LocationRowImporter extends RowImporter
{
    public function columns(): array
    {
        return ['code', 'warehouse', 'type', 'parent', 'barcode'];
    }

    protected function rowKey(array $row): string
    {
        return $row['warehouse'].'/'.$row['code'];
    }

    protected function rules(array $row): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'warehouse' => ['required', 'string'],
            'type' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (LocationType::fromLabel((string) $value) === null) {
                    $fail("Tipe lokasi \"{$value}\" tidak dikenal (area terima, area kirim, zona, rak, bin).");
                }
            }],
            'parent' => ['nullable', 'string'],
            'barcode' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function persistAll(array $rows, ImportReport $report): void
    {
        do {
            $progressed = false;
            foreach ($rows as $rowNumber => $row) {
                if ($this->parentCode($row) === null || $this->findParent($row) !== null || $this->warehouse($row) === null) {
                    $this->persistRow($rowNumber, $row, $report);
                    unset($rows[$rowNumber]);
                    $progressed = true;
                }
            }
        } while ($progressed && $rows !== []);

        foreach ($rows as $rowNumber => $row) {
            $report->fail($rowNumber, ["Lokasi induk {$row['parent']} tidak ditemukan di gudang {$row['warehouse']}."]);
        }
    }

    protected function persist(array $row): void
    {
        $warehouse = $this->warehouse($row)
            ?? throw new ImportRowException("Gudang {$row['warehouse']} tidak ditemukan.");
        $location = Location::query()->firstOrNew(['warehouse_id' => $warehouse->id, 'code' => $row['code']]);
        $parentId = $this->findParent($row)?->id;

        if ($parentId !== null) {
            (new ParentLokasiValid($location, $warehouse->id))
                ->validate('parent', $parentId, fn (string $message) => throw new ImportRowException($message));
        }

        $barcode = $row['barcode'] ?? ($location->barcode ?? 'LOC-'.$row['code']);
        if (BarcodeRegistry::isTaken($barcode, $location)) {
            throw new ImportRowException("Barcode {$barcode} sudah dipakai barang atau lokasi lain.");
        }

        $location->fill([
            'type' => LocationType::fromLabel($row['type']),
            'parent_id' => $parentId,
            'barcode' => $barcode,
        ])->save();
    }

    private function parentCode(array $row): ?string
    {
        $parent = $row['parent'] ?? null;

        return $parent === null || strcasecmp($parent, $row['warehouse']) === 0 ? null : $parent;
    }

    private function warehouse(array $row): ?Warehouse
    {
        return Warehouse::query()->where('code', $row['warehouse'])->first();
    }

    private function findParent(array $row): ?Location
    {
        $code = $this->parentCode($row);

        return $code === null ? null : Location::query()
            ->whereHas('warehouse', fn ($query) => $query->where('code', $row['warehouse']))
            ->where('code', $code)
            ->first();
    }
}
