<?php

namespace App\Services\Scan;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\OpnameLocationStatus;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\QtyTidakCocokException;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Services\DocumentNumberService;
use App\Services\OpnameService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Aksi dokumen dari halaman /scan. Perubahan stok tetap lewat StockService / OpnameService. */
class ScanDocumentService
{
    public function __construct(
        private StockService $stock,
        private OpnameService $opname,
        private DocumentNumberService $numbers,
        private ActivityLogger $activity,
    ) {}

    /** Staf hanya boleh membuat penerimaan dan transfer; dokumen lain dibuat di panel. */
    public function create(DocumentType $type, User $by, array $attributes): Document
    {
        if (! in_array($type, [DocumentType::GRN, DocumentType::TRF], true)) {
            throw new InvalidArgumentException('Dari halaman scan hanya bisa membuat penerimaan atau transfer.');
        }

        return Document::create([
            'number' => $this->numbers->next($type),
            'type' => $type,
            'warehouse_id' => $attributes['warehouse_id'] ?? Warehouse::query()->orderBy('id')->value('id'),
            'partner' => $attributes['partner'] ?? null,
            'reference' => $attributes['reference'] ?? null,
            'created_by' => $by->id,
        ]);
    }

    /** Tombol − / + : GRN & OPN mengubah hitungan, PUT/OUT/TRF mencadangkan atau melepas stok. */
    public function adjustLine(DocumentLine $line, int $delta, User $by): DocumentLine
    {
        DB::transaction(function () use ($line, $delta) {
            $document = $this->lockOpen($line->document);
            $line->refresh();

            if ($document->type->reservesSourceStock()) {
                if ($delta < 0 && $line->qty_scanned + $delta < $line->qty_packed) {
                    throw new QtyTidakCocokException('Qty picking tidak boleh lebih kecil dari qty yang sudah dipacking.');
                }
                $delta > 0 ? $this->stock->reserve($line, $delta) : $this->stock->release($line, -$delta);

                return;
            }

            if ($document->type === DocumentType::OPN) {
                $status = $document->opnameLocations()->where('location_id', $line->from_location_id)->value('status');
                if ($document->submitted_at !== null || $status !== OpnameLocationStatus::Belum->value) {
                    throw new DokumenTidakAktifException('Lokasi ini sudah selesai dihitung.');
                }
            }
            if ($line->qty_scanned + $delta < 0) {
                throw new QtyTidakCocokException('Qty tidak boleh kurang dari 0.');
            }
            $line->increment('qty_scanned', $delta);
        });

        return $line->refresh();
    }

    /** "Laporkan kosong" saat picking: rencana baris turun ke qty yang sudah terambil. */
    public function reportEmpty(DocumentLine $line, User $by): DocumentLine
    {
        DB::transaction(function () use ($line, $by) {
            $document = $this->lockOpen($line->document);
            if ($document->type !== DocumentType::OUT) {
                throw new InvalidArgumentException('Laporkan kosong hanya untuk picking.');
            }
            $line->refresh();
            $before = $line->qty_planned;
            $line->update(['qty_planned' => $line->qty_scanned]);
            $this->activity->log($by, 'picking.lokasi_kosong', $document, ['baris' => $line->id, 'qty_rencana' => $before], ['qty_rencana' => $line->qty_scanned]);
        });

        return $line->refresh();
    }

    public function complete(Document $document, User $by): Document
    {
        match ($document->type) {
            DocumentType::GRN => $this->stock->receive($document, $by),
            DocumentType::PUT => $this->stock->putaway($document, $by),
            DocumentType::TRF => $this->stock->transfer($document, $by),
            DocumentType::OUT => $this->stock->issue($document, $by),
            DocumentType::OPN => $this->opname->submit($document, $by),
            default => throw new InvalidArgumentException("Dokumen {$document->number} tidak diselesaikan dari halaman scan."),
        };

        return $document->refresh();
    }

    /**
     * "Selesai, lanjut Putaway": PUT terbuka untuk GRN ini dipakai ulang; bila tidak ada (dibatalkan, dibalik,
     * atau selesai sebagian), PUT baru berisi sisa barang GRN yang masih tersedia di lokasi terima.
     */
    public function createPutaway(Document $grn, User $by): Document
    {
        if ($grn->type !== DocumentType::GRN || $grn->status !== DocumentStatus::Selesai) {
            throw new DokumenTidakAktifException("Penerimaan {$grn->number} harus selesai sebelum putaway.");
        }

        return DB::transaction(function () use ($grn, $by) {
            Document::query()->whereKey($grn->id)->lockForUpdate()->first();
            $open = Document::query()->where('type', DocumentType::PUT)->where('related_document_id', $grn->id)->open()->first();
            if ($open !== null) {
                return $open;
            }

            $remaining = StockMovement::query()->whereIn('document_line_id', $grn->lines()->select('id'))
                ->selectRaw('item_id, to_location_id, SUM(qty) AS qty')->groupBy('item_id', 'to_location_id')->get()
                ->map(fn ($row) => [$row->item_id, $row->to_location_id, min((int) $row->qty, $this->stock->available($row->item_id, $row->to_location_id))])
                ->filter(fn (array $row) => $row[2] > 0);
            if ($remaining->isEmpty()) {
                throw new DokumenTidakAktifException("Semua barang penerimaan {$grn->number} sudah di-putaway.");
            }

            $put = Document::create([
                'number' => $this->numbers->next(DocumentType::PUT), 'type' => DocumentType::PUT,
                'warehouse_id' => $grn->warehouse_id, 'reference' => $grn->number,
                'related_document_id' => $grn->id, 'created_by' => $by->id,
            ]);
            foreach ($remaining as [$itemId, $locationId, $qty]) {
                DocumentLine::create(['document_id' => $put->id, 'item_id' => $itemId, 'from_location_id' => $locationId, 'qty_planned' => $qty]);
            }

            return $put;
        });
    }

    private function lockOpen(Document $document): Document
    {
        $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        if (! $locked->status->isOpen()) {
            throw new DokumenTidakAktifException("Dokumen {$locked->number} sudah {$locked->status->getLabel()}.");
        }

        return $locked;
    }
}
