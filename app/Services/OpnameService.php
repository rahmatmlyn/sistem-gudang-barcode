<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\OpnameLocationStatus;
use App\Enums\UserRole;
use App\Exceptions\DokumenTidakAktifException;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\OpnameLocation;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock opname per lokasi (FR-12, aturan 5). Baris OPN: item + from_location_id, qty_scanned = hitungan fisik,
 * qty_system = snapshot qty tersedia saat lokasi selesai dihitung. Selisih baru menjadi ADJ lewat approve().
 * "Menunggu approval" = status proses dengan submitted_at terisi (K7).
 */
class OpnameService
{
    public function __construct(private StockService $stock, private ActivityLogger $activity) {}

    /** Lokasi yang dihitung: lokasi terpilih beserta semua turunannya, di gudang dokumen. Hanya untuk draft. */
    public function setLocations(Document $opn, array $locationIds): void
    {
        DB::transaction(function () use ($opn, $locationIds) {
            $locked = $this->lock($opn);
            if ($locked->status !== DocumentStatus::Draft) {
                throw new DokumenTidakAktifException("Lokasi opname {$locked->number} tidak bisa diubah setelah penghitungan dimulai.");
            }

            $ids = collect(Location::query()->whereIn('id', $locationIds)->where('warehouse_id', $locked->warehouse_id)->get())
                ->flatMap(fn (Location $location) => [$location->id, ...$location->descendantIds()])
                ->unique()->values();

            $locked->opnameLocations()->whereNotIn('location_id', $ids)->delete();
            $existing = $locked->opnameLocations()->pluck('location_id');
            foreach ($ids->diff($existing) as $locationId) {
                $locked->opnameLocations()->create(['location_id' => $locationId]);
            }
        });
    }

    public function finishLocation(Document $opn, Location $location, User $by, bool $kosong): void
    {
        DB::transaction(function () use ($opn, $location, $by, $kosong) {
            $locked = $this->lockCounting($opn);
            $row = $locked->opnameLocations()->where('location_id', $location->id)->lockForUpdate()->first()
                ?? throw new InvalidArgumentException("Lokasi {$location->code} bukan bagian opname {$locked->number}.");
            if ($row->status !== OpnameLocationStatus::Belum) {
                throw new DokumenTidakAktifException("Lokasi {$location->code} sudah selesai dihitung.");
            }

            $lines = $locked->lines()->where('from_location_id', $location->id)->get()->keyBy('item_id');
            if ($kosong && $lines->sum('qty_scanned') > 0) {
                throw new InvalidArgumentException("Lokasi {$location->code} sudah berisi hasil scan, tidak bisa ditandai kosong.");
            }

            $itemIds = StockBalance::query()->where('location_id', $location->id)->where('qty', '!=', 0)->pluck('item_id')
                ->merge($lines->keys())->unique();
            foreach ($itemIds as $itemId) {
                $line = $lines[$itemId] ?? DocumentLine::create([
                    'document_id' => $locked->id, 'item_id' => $itemId, 'from_location_id' => $location->id,
                ]);
                $line->update(['qty_system' => $this->stock->available($itemId, $location->id)]);
            }

            $row->update([
                'status' => $kosong ? OpnameLocationStatus::Kosong : OpnameLocationStatus::Selesai,
                'counted_by' => $by->id,
                'counted_at' => now(),
            ]);
            $locked->update(['status' => DocumentStatus::Proses]);
            $this->activity->log($by, 'opname.lokasi_selesai', $locked, null, ['lokasi' => $location->code, 'kosong' => $kosong]);
        });
    }

    public function submit(Document $opn, User $by): void
    {
        DB::transaction(function () use ($opn, $by) {
            $locked = $this->lockCounting($opn);
            $rows = $locked->opnameLocations()->with('location')->get();
            if ($rows->isEmpty()) {
                throw new InvalidArgumentException("Opname {$locked->number} belum punya lokasi.");
            }
            $pending = $rows->where('status', OpnameLocationStatus::Belum)->map(fn (OpnameLocation $row) => $row->location->code);
            if ($pending->isNotEmpty()) {
                throw new InvalidArgumentException('Masih ada lokasi belum dihitung: '.$pending->sort()->implode(', ').'.');
            }

            $locked->update(['status' => DocumentStatus::Proses, 'submitted_at' => now()]);
            $this->activity->log($by, 'opname.diajukan', $locked, null, ['lokasi' => $rows->count()]);
        });
        $opn->refresh();
    }

    /** Aturan 5: hanya Supervisor, alasan wajib; selisih menjadi ADJ (null bila tanpa selisih). */
    public function approve(Document $opn, User $supervisor, string $reason): ?Document
    {
        $this->assertSupervisor($supervisor);
        $this->assertSubmitted($opn->fresh());

        return $this->stock->adjust($opn, $supervisor, $reason);
    }

    public function reject(Document $opn, User $supervisor, string $reason): void
    {
        $this->assertSupervisor($supervisor);
        $reason = trim($reason) !== '' ? trim($reason) : throw new InvalidArgumentException('Alasan wajib diisi.');

        DB::transaction(function () use ($opn, $supervisor, $reason) {
            $locked = $this->lock($opn);
            $this->assertSubmitted($locked);
            $locked->update(['status' => DocumentStatus::Batal, 'approval_reason' => $reason]);
            $this->activity->log($supervisor, 'opname.ditolak', $locked, ['status' => 'proses'], ['status' => 'batal', 'alasan' => $reason]);
        });
        $opn->refresh();
    }

    private function lock(Document $opn): Document
    {
        if ($opn->type !== DocumentType::OPN) {
            throw new InvalidArgumentException("Dokumen {$opn->number} bukan stock opname.");
        }

        return Document::query()->whereKey($opn->id)->lockForUpdate()->firstOrFail();
    }

    /** Terkunci, masih terbuka, dan belum diajukan (hasil hitung masih boleh berubah). */
    private function lockCounting(Document $opn): Document
    {
        $locked = $this->lock($opn);
        if (! $locked->status->isOpen() || $locked->submitted_at !== null) {
            throw new DokumenTidakAktifException("Opname {$locked->number} sudah diajukan atau tidak aktif.");
        }

        return $locked;
    }

    private function assertSubmitted(Document $opn): void
    {
        if ($opn->status !== DocumentStatus::Proses || $opn->submitted_at === null) {
            throw new DokumenTidakAktifException("Opname {$opn->number} belum diajukan untuk approval.");
        }
    }

    private function assertSupervisor(User $user): void
    {
        if (! $user->hasRole(UserRole::Supervisor)) {
            throw new AuthorizationException('Hanya Supervisor yang bisa menyetujui atau menolak selisih opname.');
        }
    }
}
