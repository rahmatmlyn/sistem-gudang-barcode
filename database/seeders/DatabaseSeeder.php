<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Data awal kecil untuk pengembangan. Data besar (1.000 SKU, 100.000 transaksi) diimpor lewat
 * `php artisan gudang:import-dummy` (M10).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            WarehouseLocationSeeder::class,
            ItemSeeder::class,
        ]);
    }
}
