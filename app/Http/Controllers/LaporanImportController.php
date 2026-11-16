<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh laporan baris gagal yang dibuat App\Filament\Actions\ImportMasterAction. */
class LaporanImportController
{
    public function __invoke(string $laporan): StreamedResponse
    {
        $path = "laporan-import/{$laporan}.csv";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, "laporan-import-{$laporan}.csv", ['Content-Type' => 'text/csv']);
    }
}
