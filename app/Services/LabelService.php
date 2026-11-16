<?php

namespace App\Services;

use App\Enums\BarcodeType;
use App\Exceptions\LabelTidakValidException;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Support\Ean13;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Label barcode PDF 50 × 30 mm (UAT-19), satu label per halaman.
 * Barang: satu label per barcode (pcs, dus, ...). Lokasi: Code 128 dari locations.barcode.
 */
class LabelService
{
    /** 50 mm × 30 mm dalam point (1 mm = 72 / 25,4 pt). Ubah bersama @page di resources/views/pdf/label.blade.php. */
    public const PAPER = [0, 0, 141.73, 85.04];

    /** Batas halaman per PDF agar dompdf tidak kehabisan memori di server. */
    public const MAX_LABELS = 1000;

    /** @param Collection<int, ItemBarcode> $barcodes */
    public function itemPdf(Collection $barcodes, int $copies = 1): string
    {
        return $this->pdf($this->itemLabels($barcodes), $copies);
    }

    /** @param Collection<int, Location> $locations */
    public function locationPdf(Collection $locations, int $copies = 1): string
    {
        return $this->pdf($locations->map(fn (Location $location) => [
            'judul' => $location->code,
            'keterangan' => trim(($location->name ?? '').' · '.$location->type->getLabel(), ' ·'),
            'kode' => $location->barcode,
            'jenis' => BarcodeType::Code128->value,
            'gambar' => $this->linear($location->barcode, BarcodeGenerator::TYPE_CODE_128),
        ])->all(), $copies);
    }

    /**
     * @param  Collection<int, ItemBarcode>  $barcodes
     * @return list<array{judul: string, keterangan: string, kode: string, jenis: string, gambar: string}>
     */
    public function itemLabels(Collection $barcodes): array
    {
        return EloquentCollection::make($barcodes->all())->loadMissing('item')->map(function (ItemBarcode $barcode) {
            $kemasan = $barcode->factor > 1 ? "{$barcode->unit} isi {$barcode->factor} {$barcode->item->base_unit}" : $barcode->unit;

            return [
                'judul' => $barcode->item->name,
                'keterangan' => "{$barcode->item->sku} · {$kemasan}",
                'kode' => $barcode->barcode,
                'jenis' => $barcode->type->value,
                'gambar' => $this->image($barcode),
            ];
        })->values()->all();
    }

    private function image(ItemBarcode $barcode): string
    {
        return match ($barcode->type) {
            BarcodeType::Ean13 => Ean13::isValid($barcode->barcode)
                ? $this->linear($barcode->barcode, BarcodeGenerator::TYPE_EAN_13)
                : throw new LabelTidakValidException("Barcode EAN-13 {$barcode->barcode} ({$barcode->item->sku}) tidak valid: check digit salah. Perbaiki di master barang."),
            BarcodeType::Code128 => $this->linear($barcode->barcode, BarcodeGenerator::TYPE_CODE_128),
            BarcodeType::Qr => (new QRCode(new QROptions([
                'outputType' => QROutputInterface::GDIMAGE_PNG, 'scale' => 6, 'quietzoneSize' => 1, 'outputBase64' => true,
            ])))->render($barcode->barcode),
        };
    }

    private function linear(string $code, string $type): string
    {
        return 'data:image/png;base64,'.base64_encode((new BarcodeGeneratorPNG)->getBarcode($code, $type, 3, 80));
    }

    /** @param list<array<string, string>> $labels */
    private function pdf(array $labels, int $copies): string
    {
        $total = count($labels) * max(1, $copies);
        if ($total > self::MAX_LABELS) {
            throw new LabelTidakValidException("Terlalu banyak label ({$total}). Maksimal ".self::MAX_LABELS.' per cetak; pilih lebih sedikit.');
        }

        $pages = [];
        foreach ($labels as $label) {
            array_push($pages, ...array_fill(0, max(1, $copies), $label));
        }

        return Pdf::loadView('pdf.label', ['labels' => $pages])->setPaper(self::PAPER)->output();
    }
}
