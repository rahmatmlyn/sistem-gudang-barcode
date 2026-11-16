<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LocationType;
use App\Events\StokBerubah;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\MelebihiRencanaException;
use App\Exceptions\QtyTidakCocokException;
use App\Exceptions\StokTidakCukupException;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Stock\AdjustmentDocumentBuilder;
use App\Services\Stock\LedgerWriter;
use App\Services\Stock\PlannedMovement;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya pintu perubahan stok (CLAUDE.md, konvensi kode).
 *
 * Model reservasi (keputusan K1): scan PUT/OUT/TRF menaikkan document_lines.qty_scanned setelah mengecek
 * qty tersedia = saldo − Σ qty_scanned baris dokumen terbuka dari lokasi yang sama. Saldo & ledger baru berubah
 * saat dokumen diselesaikan (aturan 1).
 *
 * Urutan kunci (wajib sama di semua jalur): dokumen → stock_balances (urut item, lokasi) → document_lines.
 */
class StockService
{
    public function __construct(
        private LedgerWriter $ledger,
        private AdjustmentDocumentBuilder $adjustments,
        private ActivityLogger $activity,
    ) {}

    public function available(int $itemId, int $locationId): int
    {
        $balance = (int) StockBalance::query()->where('item_id', $itemId)->where('location_id', $locationId)->value('qty');

        return $balance - $this->reservedQty($itemId, $locationId);
    }

    public function reserve(DocumentLine $line, int $qtyBase): void
    {
        $this->changeReservation($line, $this->requirePositive($qtyBase));
    }

    public function release(DocumentLine $line, int $qtyBase): void
    {
        $this->changeReservation($line, -$this->requirePositive($qtyBase));
    }

    public function receive(Document $grn, User $by): void
    {
        $this->complete($grn, DocumentType::GRN, $by, function (DocumentLine $line, Document $document) {
            return [null, $line->to_location_id ?? $this->receivingLocationId($document)];
        });
    }

    public function putaway(Document $put, User $by): void
    {
        $this->complete($put, DocumentType::PUT, $by, fn (DocumentLine $line) => $this->bothLocations($line));
    }

    public function transfer(Document $trf, User $by): void
    {
        $this->complete($trf, DocumentType::TRF, $by, fn (DocumentLine $line) => $this->bothLocations($line));
    }

    public function issue(Document $out, User $by): void
    {
        $this->complete($out, DocumentType::OUT, $by, function (DocumentLine $line) {
            if ($line->qty_scanned !== $line->qty_planned || $line->qty_packed !== $line->qty_scanned) {
                throw new QtyTidakCocokException(sprintf(
                    'Qty %s belum cocok: pesanan %d, picking %d, packing %d.',
                    $line->item->sku, $line->qty_planned, $line->qty_scanned, $line->qty_packed,
                ));
            }

            return [$line->from_location_id ?? throw new InvalidArgumentException('Baris OUT wajib punya lokasi asal.'), null];
        });
    }

    /** Selisih opname → dokumen ADJ (aturan 5). Mengembalikan null bila tidak ada selisih. */
    public function adjust(Document $opn, User $approver, string $reason): ?Document
    {
        $reason = $this->requireReason($reason);
        $this->assertType($opn, DocumentType::OPN);

        [$adjustment, $itemIds] = DB::transaction(function () use ($opn, $approver, $reason) {
            $locked = $this->lockOpen($opn);
            $differences = $locked->lines()->whereNotNull('qty_system')->whereColumn('qty_scanned', '!=', 'qty_system')->get()
                ->map(function (DocumentLine $line) {
                    $locationId = $line->from_location_id ?? $line->to_location_id;
                    $difference = $line->qty_scanned - $line->qty_system;

                    return [
                        'item_id' => $line->item_id,
                        'from' => $difference < 0 ? $locationId : null,
                        'to' => $difference > 0 ? $locationId : null,
                        'qty' => abs($difference),
                    ];
                })->values()->all();

            $adjustment = null;
            $itemIds = [];
            if ($differences !== []) {
                [$adjustment, $movements] = $this->adjustments->build($locked, $approver, $reason, $differences, ['related_document_id' => $locked->id]);
                $itemIds = $this->ledger->apply($movements, DocumentType::ADJ, $approver);
            }

            $now = now();
            $before = $locked->status;
            $locked->update([
                'status' => DocumentStatus::Selesai, 'completed_at' => $now, 'completed_by' => $approver->id,
                'approved_by' => $approver->id, 'approved_at' => $now, 'approval_reason' => $reason,
            ]);
            $this->activity->log($approver, 'opname.disetujui', $locked, ['status' => $before->value], [
                'status' => DocumentStatus::Selesai->value, 'alasan' => $reason, 'adj' => $adjustment?->number,
            ]);

            return [$adjustment, $itemIds];
        });

        $opn->refresh();
        $this->announce($itemIds);

        return $adjustment?->load('lines');
    }

    /** Koreksi dokumen selesai lewat transaksi balik (aturan 3). */
    public function reverse(Document $document, User $by, string $reason): Document
    {
        $reason = $this->requireReason($reason);

        [$reversal, $itemIds] = DB::transaction(function () use ($document, $by, $reason) {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DocumentStatus::Selesai) {
                throw new DokumenTidakAktifException("Dokumen {$locked->number} belum selesai, tidak ada transaksi untuk dibalik.");
            }
            if (Document::query()->where('reversal_of_id', $locked->id)->exists()) {
                throw new DokumenTidakAktifException("Dokumen {$locked->number} sudah pernah dibalik.");
            }

            $lines = StockMovement::query()->whereIn('document_line_id', $locked->lines()->select('id'))->orderBy('id')->get()
                ->map(fn (StockMovement $movement) => [
                    'item_id' => $movement->item_id, 'from' => $movement->to_location_id, 'to' => $movement->from_location_id, 'qty' => $movement->qty,
                ])->all();
            if ($lines === []) {
                throw new QtyTidakCocokException("Dokumen {$locked->number} tidak punya transaksi stok untuk dibalik.");
            }

            [$reversal, $movements] = $this->adjustments->build($locked, $by, $reason, $lines, ['reversal_of_id' => $locked->id]);
            $itemIds = $this->ledger->apply($movements, DocumentType::ADJ, $by);
            $this->activity->log($by, 'dokumen.dibalik', $locked, null, ['pembalik' => $reversal->number, 'alasan' => $reason]);

            return [$reversal, $itemIds];
        });

        $this->announce($itemIds);

        return $reversal->load('lines');
    }

    /**
     * @param  Closure(DocumentLine, Document): array{0: int|null, 1: int|null}  $locations  [asal, tujuan] per baris
     */
    private function complete(Document $document, DocumentType $type, User $by, Closure $locations): void
    {
        $this->assertType($document, $type);

        $itemIds = DB::transaction(function () use ($document, $type, $by, $locations) {
            $locked = $this->lockOpen($document);
            $lines = $locked->lines()->with('item')->orderBy('id')->get();

            $movements = $lines->map(function (DocumentLine $line) use ($locked, $locations) {
                [$from, $to] = $locations($line, $locked);

                return $line->qty_scanned > 0 ? new PlannedMovement($line->item_id, $from, $to, $line->qty_scanned, $line->id) : null;
            })->filter()->values()->all();

            if ($movements === []) {
                throw new QtyTidakCocokException("Dokumen {$locked->number} belum punya qty yang di-scan.");
            }

            $itemIds = $this->ledger->apply($movements, $type, $by);
            $before = $locked->status;
            $locked->update(['status' => DocumentStatus::Selesai, 'completed_at' => now(), 'completed_by' => $by->id]);
            $this->activity->log($by, 'dokumen.selesai', $locked, ['status' => $before->value], [
                'status' => DocumentStatus::Selesai->value, 'nomor' => $locked->number, 'jumlah_movement' => count($movements),
            ]);

            return $itemIds;
        });

        $document->refresh();
        $this->announce($itemIds);
    }

    private function changeReservation(DocumentLine $line, int $delta): void
    {
        DB::transaction(function () use ($line, $delta) {
            $document = Document::query()->whereKey($line->document_id)->sharedLock()->firstOrFail();
            if (! $document->type->reservesSourceStock() || $line->from_location_id === null) {
                throw new InvalidArgumentException('Reservasi hanya untuk baris PUT, OUT, atau TRF yang punya lokasi asal.');
            }
            $this->assertOpen($document);

            $balance = (int) StockBalance::query()->where('item_id', $line->item_id)->where('location_id', $line->from_location_id)
                ->lockForUpdate()->value('qty');
            $fresh = DocumentLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();

            if ($delta > 0) {
                if ($fresh->qty_planned > 0 && $fresh->qty_scanned + $delta > $fresh->qty_planned) {
                    throw new MelebihiRencanaException("Melebihi rencana: sudah {$fresh->qty_scanned} dari {$fresh->qty_planned}, ditambah {$delta}.");
                }
                $available = $balance - $this->reservedQty($line->item_id, $line->from_location_id);
                if ($available < $delta) {
                    throw new StokTidakCukupException($line->item_id, $line->from_location_id, max($available, 0), $delta);
                }
            } elseif ($fresh->qty_scanned + $delta < 0) {
                throw new InvalidArgumentException("Qty tidak bisa dikurangi di bawah 0 (saat ini {$fresh->qty_scanned}).");
            }

            $fresh->increment('qty_scanned', $delta);
            $line->qty_scanned = $fresh->qty_scanned;
            $line->syncOriginalAttribute('qty_scanned');
        });
    }

    private function reservedQty(int $itemId, int $locationId): int
    {
        return (int) DocumentLine::query()
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->where('document_lines.item_id', $itemId)
            ->where('document_lines.from_location_id', $locationId)
            ->whereIn('documents.status', DocumentStatus::openValues())
            ->whereIn('documents.type', array_map(fn (DocumentType $type) => $type->value, array_filter(
                DocumentType::cases(), fn (DocumentType $type) => $type->reservesSourceStock(),
            )))
            ->sum('document_lines.qty_scanned');
    }

    private function lockOpen(Document $document): Document
    {
        $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        $this->assertOpen($locked);

        return $locked;
    }

    private function assertOpen(Document $document): void
    {
        if (! $document->status->isOpen()) {
            throw new DokumenTidakAktifException("Dokumen {$document->number} berstatus {$document->status->getLabel()}.");
        }
    }

    private function assertType(Document $document, DocumentType $type): void
    {
        if ($document->type !== $type) {
            throw new InvalidArgumentException("Dokumen {$document->number} bertipe {$document->type->value}, bukan {$type->value}.");
        }
    }

    /**
     * Baris yang belum di-scan (putaway sebagian) tidak menghasilkan movement, jadi lokasinya boleh kosong.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function bothLocations(DocumentLine $line): array
    {
        if ($line->qty_scanned > 0 && ($line->from_location_id === null || $line->to_location_id === null)) {
            throw new InvalidArgumentException('Baris putaway/transfer wajib punya lokasi asal dan tujuan.');
        }

        return [$line->from_location_id, $line->to_location_id];
    }

    private function receivingLocationId(Document $document): int
    {
        return Location::query()->where('warehouse_id', $document->warehouse_id)->where('type', LocationType::AreaTerima)
            ->orderBy('id')->value('id') ?? throw new InvalidArgumentException('Gudang belum punya lokasi area terima.');
    }

    private function requirePositive(int $qty): int
    {
        return $qty > 0 ? $qty : throw new InvalidArgumentException('Qty harus lebih dari 0.');
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);

        return $reason !== '' ? $reason : throw new InvalidArgumentException('Alasan wajib diisi.');
    }

    /** @param  list<int>  $itemIds */
    private function announce(array $itemIds): void
    {
        if ($itemIds !== []) {
            StokBerubah::dispatch($itemIds);
        }
    }
}
