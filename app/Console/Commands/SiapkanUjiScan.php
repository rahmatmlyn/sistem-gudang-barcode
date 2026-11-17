<?php

namespace App\Console\Commands;

use App\Enums\BarcodeType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureTokenUjiScan;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\DocumentService;
use App\Services\StockService;
use App\Support\Ean13;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan data untuk tools/uji_scan_bersamaan.py (UAT-26, UAT-27). HANYA untuk lokal/staging.
 * Barang khusus SKU-UJI-SCAN dengan stok tersedia tepat --stok di --lokasi; stok berlebih dipindah ke area terima
 * lewat StockService. Dokumen OUT berstatus proses langsung berisi satu baris di lokasi itu (rencana boleh > stok).
 */
class SiapkanUjiScan extends Command
{
    protected $signature = 'gudang:siapkan-uji-scan {--stok=1} {--rencana=10} {--lokasi=A-01-01} {--base=http://127.0.0.1:8000}';

    protected $description = 'Siapkan stok, dokumen OUT, dan token staf untuk tools/uji_scan_bersamaan.py';

    private const SKU = 'SKU-UJI-SCAN';

    private const REFERENCE = 'UJI-SCAN';

    public function handle(StockService $stock, DocumentService $documents, DocumentNumberService $numbers): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Perintah ini tidak boleh dijalankan di production.');

            return self::FAILURE;
        }

        $location = Location::query()->where('code', $this->option('lokasi'))->firstOrFail();
        $receiving = Location::query()->where('warehouse_id', $location->warehouse_id)->where('type', LocationType::AreaTerima)->firstOrFail();
        $staf = User::query()->where('role', UserRole::Staf)->orderBy('id')->firstOrFail();
        $item = $this->ujiItem();
        $barcode = $item->barcodes()->value('barcode');

        Document::query()->where('reference', self::REFERENCE)->open()->get()
            ->each(fn (Document $document) => $documents->cancel($document, $staf, 'Uji scan disiapkan ulang'));

        $difference = (int) $this->option('stok') - $stock->available($item->id, $location->id);
        if ($difference !== 0) {
            [$type, $from, $to] = $difference > 0 ? [DocumentType::GRN, null, $location] : [DocumentType::TRF, $location, $receiving];
            $document = $this->document($numbers, $type, $location->warehouse_id, $staf, DocumentStatus::Draft);
            DocumentLine::create(['document_id' => $document->id, 'item_id' => $item->id, 'from_location_id' => $from?->id,
                'to_location_id' => $to->id, 'qty_planned' => abs($difference), 'qty_scanned' => abs($difference)]);
            $difference > 0 ? $stock->receive($document, $staf) : $stock->transfer($document, $staf);
        }

        $out = $this->document($numbers, DocumentType::OUT, $location->warehouse_id, $staf, DocumentStatus::Proses);
        DocumentLine::create(['document_id' => $out->id, 'item_id' => $item->id, 'from_location_id' => $location->id,
            'qty_planned' => (int) $this->option('rencana')]);

        $token = $staf->createToken('uji-scan', [EnsureTokenUjiScan::ABILITY])->plainTextToken;
        $args = "--base {$this->option('base')} --token '{$token}' --barcode {$barcode} --location {$location->code} --doc {$out->number}";
        $this->info("Stok tersedia {$item->sku} di {$location->code}: {$stock->available($item->id, $location->id)}");
        $this->info("Dokumen OUT: {$out->number} (rencana {$this->option('rencana')}), token staf: {$staf->username}");
        $this->line("python3 tools/uji_scan_bersamaan.py race {$args} --clients 10");
        $this->line("python3 tools/uji_scan_bersamaan.py load {$args} --clients 10 --scans 100");

        return self::SUCCESS;
    }

    private function ujiItem(): Item
    {
        return DB::transaction(function () {
            $item = Item::query()->firstOrCreate(['sku' => self::SKU], ['name' => 'Barang Uji Scan', 'base_unit' => 'pcs', 'category' => 'Uji']);
            $item->barcodes()->firstOrCreate([], [
                'barcode' => Ean13::complete('299000000001'), 'type' => BarcodeType::Ean13, 'unit' => 'pcs', 'factor' => 1,
            ]);

            return $item;
        });
    }

    private function document(DocumentNumberService $numbers, DocumentType $type, int $warehouseId, User $by, DocumentStatus $status): Document
    {
        return Document::create([
            'number' => $numbers->next($type), 'type' => $type, 'status' => $status, 'warehouse_id' => $warehouseId,
            'reference' => self::REFERENCE, 'created_by' => $by->id,
        ]);
    }
}
