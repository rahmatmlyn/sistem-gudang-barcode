<?php

namespace App\Services\Scan;

use App\Models\DocumentLine;
use App\Models\ItemBarcode;
use App\Models\Location;

/**
 * Respons sukses POST /api/scan. Kontrak: lihat docs/superpowers/plans (Kontrak inti).
 * Tidak pernah memuat qty_system (hitung buta opname, UAT-14).
 */
final class ScanResult
{
    private function __construct(private array $body) {}

    public static function lokasi(Location $location, string $pesan): self
    {
        return new self([
            'status' => 'ok', 'pesan' => $pesan, 'jenis' => 'lokasi',
            'lokasi' => ['kode' => $location->code, 'nama' => $location->name],
        ]);
    }

    /** @param array{id: int, qty_scan: int, qty_rencana: int, qty_pack: int} $baris */
    public static function barang(ItemBarcode $barcode, int $qtyBase, array $baris, string $pesan): self
    {
        return new self([
            'status' => 'ok', 'pesan' => $pesan, 'jenis' => 'barang',
            'item' => ['sku' => $barcode->item->sku, 'nama' => $barcode->item->name, 'satuan' => $barcode->item->base_unit],
            'kemasan' => ['satuan' => $barcode->unit, 'faktor' => $barcode->factor],
            'qty_dasar' => $qtyBase,
            'baris' => $baris,
        ]);
    }

    /** @return array{id: int, qty_scan: int, qty_rencana: int, qty_pack: int} */
    public static function baris(DocumentLine $line): array
    {
        return [
            'id' => $line->id,
            'qty_scan' => $line->qty_scanned,
            'qty_rencana' => $line->qty_planned,
            'qty_pack' => $line->qty_packed,
        ];
    }

    public static function pesanBarang(ItemBarcode $barcode, int $qtyBase, string $aksi): string
    {
        $kemasan = $barcode->factor > 1 ? " ({$barcode->unit} isi {$barcode->factor})" : '';

        return "{$aksi} +{$qtyBase} {$barcode->item->base_unit} {$barcode->item->name}{$kemasan}";
    }

    public function toArray(): array
    {
        return $this->body;
    }
}
