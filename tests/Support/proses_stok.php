<?php

/*
 * Proses anak untuk tests/Concurrency: setiap proses punya koneksi database sendiri.
 * Pemakaian: php tests/Support/proses_stok.php <mode> <mulai_epoch_ms> <argumen...>
 *   reserve  <line_id> <qty> <ulang>   → satu baris output per percobaan
 *   transfer <document_id> <user_id>
 *   nomor    <tipe> <jumlah>
 * Output per baris: OK[:nilai] | STOK_KURANG | GAGAL:<pesan>
 */

use App\Enums\DocumentType;
use App\Exceptions\StokTidakCukupException;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\StockService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $mode, $mulaiMs] = $argv;
$argumen = array_slice($argv, 3);

while ((int) (microtime(true) * 1000) < (int) $mulaiMs) {
    usleep(200);
}

$coba = function (Closure $aksi): string {
    try {
        $hasil = $aksi();

        return 'OK'.($hasil === null ? '' : ':'.$hasil);
    } catch (StokTidakCukupException) {
        return 'STOK_KURANG';
    } catch (Throwable $exception) {
        return 'GAGAL:'.str_replace("\n", ' ', $exception->getMessage());
    }
};

$stok = app(StockService::class);

switch ($mode) {
    case 'reserve':
        [$lineId, $qty, $ulang] = array_map('intval', $argumen);
        for ($index = 0; $index < $ulang; $index++) {
            echo $coba(fn () => $stok->reserve(DocumentLine::findOrFail($lineId), $qty)), PHP_EOL;
        }
        break;

    case 'transfer':
        [$documentId, $userId] = array_map('intval', $argumen);
        echo $coba(fn () => $stok->transfer(Document::findOrFail($documentId), User::findOrFail($userId))), PHP_EOL;
        break;

    case 'nomor':
        [$tipe, $jumlah] = [$argumen[0], (int) $argumen[1]];
        for ($index = 0; $index < $jumlah; $index++) {
            echo $coba(fn () => app(DocumentNumberService::class)->next(DocumentType::from($tipe))), PHP_EOL;
        }
        break;

    default:
        fwrite(STDERR, "Mode tidak dikenal: {$mode}\n");
        exit(2);
}
