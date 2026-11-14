<?php

namespace Tests\Support;

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

/**
 * Menjalankan beberapa proses PHP (tests/Support/proses_stok.php) yang mulai di milidetik yang sama.
 */
final class Bersamaan
{
    /**
     * @param  list<list<string|int>>  $daftarArgumen  [mode, argumen...] per proses
     * @return list<string> semua baris output dari semua proses
     */
    public static function jalankan(array $daftarArgumen, int $jedaMulaiMs = 2500): array
    {
        $mulai = (int) (microtime(true) * 1000) + $jedaMulaiMs;
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        $hasil = Process::pool(function (Pool $pool) use ($daftarArgumen, $mulai, $env) {
            foreach ($daftarArgumen as $argumen) {
                $mode = array_shift($argumen);
                $pool->path(base_path())->env($env)->timeout(60)
                    ->command([PHP_BINARY, 'tests/Support/proses_stok.php', $mode, (string) $mulai, ...array_map('strval', $argumen)]);
            }
        })->start()->wait();

        $baris = [];
        foreach ($hasil as $proses) {
            if (! $proses->successful()) {
                throw new \RuntimeException('Proses anak gagal: '.$proses->errorOutput());
            }
            array_push($baris, ...array_filter(explode(PHP_EOL, trim($proses->output()))));
        }

        return $baris;
    }
}
