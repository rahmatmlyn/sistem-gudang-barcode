<?php

namespace Tests\Support;

use App\Enums\DocumentType;
use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * Fixture gudang kecil untuk test stok: area terima, area kirim, dua bin, satu staf, satu supervisor.
 */
final class Gudang
{
    private function __construct(
        public Warehouse $gudang,
        public Location $rcv,
        public Location $shp,
        public Location $binA,
        public Location $binB,
        public User $staf,
        public User $supervisor,
    ) {}

    public static function siapkan(): self
    {
        $gudang = Warehouse::factory()->create(['code' => 'GU']);
        $lokasi = fn (string $code, LocationType $type) => Location::factory()->for($gudang)->ofType($type)
            ->create(['code' => $code, 'barcode' => "LOC-{$code}"]);

        return new self(
            $gudang,
            $lokasi('RCV-01', LocationType::AreaTerima),
            $lokasi('SHP-01', LocationType::AreaKirim),
            $lokasi('A-01-01', LocationType::Bin),
            $lokasi('B-01-01', LocationType::Bin),
            User::factory()->create(['role' => UserRole::Staf]),
            User::factory()->create(['role' => UserRole::Supervisor]),
        );
    }

    public function barang(array $attributes = []): Item
    {
        return Item::factory()->create($attributes);
    }

    /**
     * @param  list<array{item: Item, from?: Location|null, to?: Location|null, planned?: int, scanned?: int, packed?: int, system?: int|null}>  $lines
     */
    public function dokumen(DocumentType $type, array $lines = [], array $attributes = []): Document
    {
        $document = Document::factory()->ofType($type)->create([
            'warehouse_id' => $this->gudang->id,
            'created_by' => $this->staf->id,
            ...$attributes,
        ]);

        foreach ($lines as $line) {
            $this->baris($document, $line);
        }

        return $document->load('lines');
    }

    /** @param  array{item: Item, from?: Location|null, to?: Location|null, planned?: int, scanned?: int, packed?: int, system?: int|null}  $line */
    public function baris(Document $document, array $line): DocumentLine
    {
        return DocumentLine::create([
            'document_id' => $document->id,
            'item_id' => $line['item']->id,
            'from_location_id' => ($line['from'] ?? null)?->id,
            'to_location_id' => ($line['to'] ?? null)?->id,
            'qty_planned' => $line['planned'] ?? 0,
            'qty_scanned' => $line['scanned'] ?? 0,
            'qty_packed' => $line['packed'] ?? 0,
            'qty_system' => $line['system'] ?? null,
        ]);
    }

    /** Stok awal lewat jalur resmi: GRN yang diselesaikan langsung ke lokasi tujuan. */
    public function stokAwal(Item $item, Location $location, int $qty): void
    {
        $grn = $this->dokumen(DocumentType::GRN, [['item' => $item, 'to' => $location, 'scanned' => $qty]]);

        app(StockService::class)->receive($grn, $this->staf);
    }

    public static function saldo(Item $item, Location $location): int
    {
        return (int) StockBalance::query()
            ->where('item_id', $item->id)
            ->where('location_id', $location->id)
            ->value('qty');
    }

    /** Untuk setiap pasangan item–lokasi: Σ masuk − Σ keluar di ledger == stock_balances.qty, dan tidak ada saldo negatif. */
    public static function assertLedgerCocok(): void
    {
        $selisih = DB::select(<<<'SQL'
            WITH ledger AS (
                SELECT item_id, location_id, SUM(qty) AS qty FROM (
                    SELECT item_id, to_location_id AS location_id, qty FROM stock_movements WHERE to_location_id IS NOT NULL
                    UNION ALL
                    SELECT item_id, from_location_id, -qty FROM stock_movements WHERE from_location_id IS NOT NULL
                ) arus GROUP BY item_id, location_id
            )
            SELECT COALESCE(l.item_id, b.item_id) AS item_id, COALESCE(l.location_id, b.location_id) AS location_id,
                   COALESCE(l.qty, 0) AS ledger, COALESCE(b.qty, 0) AS saldo
            FROM ledger l
            FULL OUTER JOIN stock_balances b ON b.item_id = l.item_id AND b.location_id = l.location_id
            WHERE COALESCE(l.qty, 0) <> COALESCE(b.qty, 0)
        SQL);

        Assert::assertSame([], $selisih, 'Ledger tidak cocok dengan saldo: '.json_encode($selisih));
        Assert::assertSame(0, StockBalance::where('qty', '<', 0)->count(), 'Ada saldo negatif');
    }
}
