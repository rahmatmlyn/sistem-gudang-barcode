<?php

use App\Enums\DocumentType;
use App\Exceptions\StokTidakCukupException;
use App\Models\StockBalance;
use App\Services\StockService;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\Gudang;

it('ledger selalu cocok dengan saldo setelah rangkaian transaksi acak', function () {
    $g = Gudang::siapkan();
    $stok = app(StockService::class);
    $barang = collect(range(1, 4))->map(fn () => $g->barang());
    $rak = [$g->binA, $g->binB];
    $acak = new Randomizer(new Mt19937(20261113));
    $ditolak = 0;

    for ($langkah = 0; $langkah < 80; $langkah++) {
        $item = $barang[$acak->getInt(0, 3)];
        $asal = $rak[$acak->getInt(0, 1)];
        $tujuan = $asal->is($g->binA) ? $g->binB : $g->binA;
        $qty = $acak->getInt(1, 15);

        try {
            match ($acak->getInt(0, 4)) {
                0 => $stok->receive($g->dokumen(DocumentType::GRN, [['item' => $item, 'scanned' => $qty]]), $g->staf),
                1 => $stok->putaway($g->dokumen(DocumentType::PUT, [['item' => $item, 'from' => $g->rcv, 'to' => $asal, 'scanned' => $qty]]), $g->staf),
                2 => $stok->transfer($g->dokumen(DocumentType::TRF, [['item' => $item, 'from' => $asal, 'to' => $tujuan, 'scanned' => $qty]]), $g->staf),
                3 => $stok->issue($g->dokumen(DocumentType::OUT, [['item' => $item, 'from' => $asal, 'planned' => $qty, 'scanned' => $qty, 'packed' => $qty]]), $g->staf),
                4 => $stok->adjust($g->dokumen(DocumentType::OPN, [[
                    'item' => $item, 'from' => $asal, 'scanned' => $qty, 'system' => Gudang::saldo($item, $asal),
                ]]), $g->supervisor, 'Opname acak'),
            };
        } catch (StokTidakCukupException) {
            $ditolak++;
        }
    }

    Gudang::assertLedgerCocok();
    expect($ditolak)->toBeGreaterThan(0, 'Rangkaian harus juga menguji penolakan stok kurang');
    expect(StockBalance::sum('qty'))->toBeGreaterThan(0);
});
