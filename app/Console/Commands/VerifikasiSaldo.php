<?php

namespace App\Console\Commands;

use App\Services\LedgerVerifier;
use Illuminate\Console\Command;

class VerifikasiSaldo extends Command
{
    protected $signature = 'gudang:verifikasi-saldo {--batas=50 : jumlah selisih yang ditampilkan}';

    protected $description = 'Bandingkan saldo (stock_balances) dengan total ledger (stock_movements); exit 1 bila ada selisih';

    public function handle(LedgerVerifier $verifier): int
    {
        $start = microtime(true);
        $differences = $verifier->differences();
        $negative = $verifier->negativeBalances();
        $seconds = number_format(microtime(true) - $start, 2, ',', '.');

        if ($differences->isEmpty() && $negative === 0) {
            $this->info("Saldo cocok dengan ledger untuk semua barang dan lokasi ({$seconds} dtk).");

            return self::SUCCESS;
        }

        $this->error("Ditemukan {$differences->count()} selisih dan {$negative} saldo negatif ({$seconds} dtk).");
        $this->table(['SKU', 'Lokasi', 'Ledger', 'Saldo', 'Selisih'], $differences->take((int) $this->option('batas'))
            ->map(fn (object $row) => [$row->sku, $row->lokasi, $row->ledger, $row->saldo, $row->saldo - $row->ledger])->all());

        return self::FAILURE;
    }
}
