<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Imports\Dummy\MovementImporter;
use App\Imports\ItemBarcodeRowImporter;
use App\Imports\ItemRowImporter;
use App\Imports\LocationRowImporter;
use App\Imports\RowImporter;
use App\Imports\SpreadsheetReader;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Muat data dummy (database/dummy) untuk uji performa dan UAT-29. Hanya untuk lokal/staging. */
class ImportDummyData extends Command
{
    protected $signature = 'gudang:import-dummy {--fresh : kosongkan database (migrate:fresh) lebih dulu} {--path=database/dummy}';

    protected $description = 'Impor master, ledger 100.000 transaksi, dan saldo dari CSV dummy';

    private const ROLES = ['Staf Gudang' => UserRole::Staf, 'Supervisor' => UserRole::Supervisor, 'Admin' => UserRole::Admin];

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Data dummy tidak boleh dimuat di production.');

            return self::FAILURE;
        }
        ini_set('memory_limit', '1G');
        $path = rtrim(base_path($this->option('path')), '/');
        $start = microtime(true);

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        } elseif (StockMovement::query()->exists()) {
            $this->error('Database sudah berisi transaksi stok. Jalankan dengan --fresh untuk mengosongkan database lebih dulu.');

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(fn () => Model::withoutEvents(fn () => $this->importAll($path)));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage().' Tidak ada data yang disimpan.');

            return self::FAILURE;
        }

        app(ActivityLogger::class)->log(null, 'import.dummy', null, null, $result);
        $this->table(['Tabel', 'Baris'], collect($result)->map(fn ($count, $table) => [$table, number_format($count, 0, ',', '.')])->values()->all());
        $this->info('Selesai dalam '.number_format(microtime(true) - $start, 1, ',', '.').' dtk. Jalankan gudang:verifikasi-saldo untuk memeriksa saldo.');

        return self::SUCCESS;
    }

    /** @return array<string, int> jumlah baris per tabel; RuntimeException membatalkan seluruh import */
    private function importAll(string $path): ?array
    {
        $warehouseIds = [];
        foreach (SpreadsheetReader::rows("{$path}/locations.csv") as $number => $row) {
            if ($number > 1 && ! isset($warehouseIds[$row['warehouse']])) {
                $warehouseIds[$row['warehouse']] = Warehouse::query()->firstOrCreate(
                    ['code' => $row['warehouse']], ['name' => $row['warehouse'] === 'GU' ? 'Gudang Utama' : $row['warehouse']],
                )->id;
            }
        }

        $users = $this->importUsers("{$path}/users.csv");
        foreach (['locations' => LocationRowImporter::class, 'items' => ItemRowImporter::class, 'item_barcodes' => ItemBarcodeRowImporter::class] as $file => $importer) {
            $this->master($file, app($importer), "{$path}/{$file}.csv");
        }

        $ledger = (new MovementImporter)->import("{$path}/stock_movements.csv", reset($warehouseIds));
        $balances = $this->importBalances("{$path}/stock_balances.csv");

        return [
            'warehouses' => count($warehouseIds), 'users' => $users,
            'locations' => DB::table('locations')->count(), 'items' => DB::table('items')->count(),
            'item_barcodes' => DB::table('item_barcodes')->count(), 'documents' => $ledger['dokumen'],
            'document_lines' => $ledger['baris'], 'stock_movements' => $ledger['mutasi'], 'stock_balances' => $balances,
        ];
    }

    private function importUsers(string $file): int
    {
        $password = Hash::make('password');
        $count = 0;
        foreach (SpreadsheetReader::rows($file) as $number => $row) {
            if ($number > 1) {
                User::query()->updateOrCreate(['username' => $row['username']], [
                    'name' => $row['name'], 'role' => self::ROLES[$row['role']] ?? UserRole::Staf, 'password' => $password,
                ]);
                $count++;
            }
        }

        return $count;
    }

    private function master(string $name, RowImporter $importer, string $file): void
    {
        $report = $importer->import($file);
        if ($report->jumlahGagal() === 0) {
            return;
        }

        foreach (array_slice($report->gagal, 0, 20, true) as $row => $messages) {
            $this->line("  baris {$row}: ".implode('; ', $messages));
        }

        throw new RuntimeException("Impor {$name} gagal: {$report->ringkasan()}");
    }

    private function importBalances(string $file): int
    {
        $items = DB::table('items')->pluck('id', 'sku');
        $locations = DB::table('locations')->pluck('id', 'code');
        $rows = [];
        foreach (SpreadsheetReader::rows($file) as $number => $row) {
            if ($number > 1) {
                $rows[] = ['item_id' => $items[$row['sku']], 'location_id' => $locations[$row['location']], 'qty' => (int) $row['qty'],
                    'created_at' => now(), 'updated_at' => now()];
            }
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('stock_balances')->insert($chunk);
        }

        return count($rows);
    }
}
