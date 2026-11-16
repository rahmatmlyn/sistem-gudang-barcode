<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LocationType;
use App\Exceptions\AlokasiGagalException;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\QtyTidakCocokException;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Rilis ke picking" (FR-09, UAT-07): memecah baris pesanan keluar menjadi baris per lokasi simpan,
 * urut kode lokasi. Tidak mengubah saldo: stok baru dicadangkan saat di-scan (StockService::reserve).
 *
 * Stok bebas di satu lokasi = tersedia − sisa rencana pesanan keluar lain yang sudah dirilis.
 * Rilis diserialkan dengan advisory lock agar dua rilis bersamaan tidak menjanjikan stok yang sama.
 */
class PickingAllocator
{
    private const ADVISORY_LOCK_KEY = 7_202_611; // unik per proses yang memakai pg_advisory_xact_lock

    private const NON_STORAGE = [LocationType::AreaTerima, LocationType::AreaKirim];

    public function __construct(private StockService $stock, private ActivityLogger $activity) {}

    public function allocate(Document $out, User $by): void
    {
        if ($out->type !== DocumentType::OUT) {
            throw new InvalidArgumentException("Dokumen {$out->number} bukan pesanan keluar.");
        }

        DB::transaction(function () use ($out, $by) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::ADVISORY_LOCK_KEY]);
            $locked = Document::query()->whereKey($out->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DocumentStatus::Draft) {
                throw new DokumenTidakAktifException("Pesanan {$locked->number} sudah dirilis atau tidak aktif.");
            }

            $needed = $locked->lines()->get()->groupBy('item_id')->map(fn ($lines) => (int) $lines->sum('qty_planned'));
            if ($needed->isEmpty() || $needed->contains(fn (int $qty) => $qty <= 0)) {
                throw new QtyTidakCocokException("Pesanan {$locked->number} harus punya barang dengan qty lebih dari 0.");
            }

            [$plan, $shortages] = $this->plan($needed->all(), $locked);
            if ($shortages !== []) {
                throw new AlokasiGagalException('Stok tidak cukup untuk dirilis: '.implode('; ', $shortages).'.');
            }

            $locked->lines()->delete();
            foreach ($plan as [$itemId, $locationId, $qty]) {
                DocumentLine::create(['document_id' => $locked->id, 'item_id' => $itemId, 'from_location_id' => $locationId, 'qty_planned' => $qty]);
            }
            $locked->update(['status' => DocumentStatus::Proses]);
            $this->activity->log($by, 'pesanan.dirilis', $locked, ['status' => 'draft'], ['status' => 'proses', 'baris' => count($plan)]);
        });

        $out->refresh();
    }

    /**
     * @param  array<int, int>  $needed  item_id → qty
     * @return array{0: list<array{0: int, 1: int, 2: int}>, 1: list<string>} [item, lokasi, qty] dan pesan kekurangan
     */
    private function plan(array $needed, Document $out): array
    {
        $plan = [];
        $shortages = [];
        foreach ($needed as $itemId => $qty) {
            $remaining = $qty;
            foreach ($this->candidates($itemId, $out) as [$locationId, $free]) {
                $take = min($free, $remaining);
                if ($take > 0) {
                    $plan[] = [$itemId, $locationId, $take];
                    $remaining -= $take;
                }
                if ($remaining === 0) {
                    break;
                }
            }
            if ($remaining > 0) {
                $sku = Item::query()->whereKey($itemId)->value('sku');
                $shortages[] = "{$sku} butuh {$qty}, bisa dialokasikan ".($qty - $remaining);
            }
        }

        return [$plan, $shortages];
    }

    /** @return list<array{0: int, 1: int}> [location_id, qty bebas] urut kode lokasi */
    private function candidates(int $itemId, Document $out): array
    {
        $locations = DB::table('stock_balances')
            ->join('locations', 'locations.id', '=', 'stock_balances.location_id')
            ->where('stock_balances.item_id', $itemId)
            ->where('stock_balances.qty', '>', 0)
            ->where('locations.warehouse_id', $out->warehouse_id)
            ->where('locations.is_active', true)
            ->whereNotIn('locations.type', array_map(fn (LocationType $type) => $type->value, self::NON_STORAGE))
            ->orderBy('locations.code')
            ->pluck('locations.id');

        return $locations->map(fn (int $locationId) => [
            $locationId,
            $this->stock->available($itemId, $locationId) - $this->promisedElsewhere($itemId, $locationId, $out),
        ])->all();
    }

    /** Sisa rencana (belum di-scan) pesanan keluar lain yang sudah dirilis dari lokasi ini. */
    private function promisedElsewhere(int $itemId, int $locationId, Document $out): int
    {
        return (int) DocumentLine::query()
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->where('documents.type', DocumentType::OUT)
            ->where('documents.status', DocumentStatus::Proses)
            ->where('documents.id', '!=', $out->id)
            ->where('document_lines.item_id', $itemId)
            ->where('document_lines.from_location_id', $locationId)
            ->sum(DB::raw('GREATEST(document_lines.qty_planned - document_lines.qty_scanned, 0)'));
    }
}
