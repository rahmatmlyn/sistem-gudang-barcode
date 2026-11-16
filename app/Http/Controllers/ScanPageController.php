<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/** Halaman /scan untuk Staf Gudang (layout sendiri, bukan Filament). Data diambil lewat /api/*. */
class ScanPageController
{
    public const MODES = [
        'penerimaan' => ['label' => 'Penerimaan', 'keterangan' => 'Terima barang dari supplier'],
        'putaway' => ['label' => 'Putaway', 'keterangan' => 'Simpan barang ke rak'],
        'picking' => ['label' => 'Picking', 'keterangan' => 'Ambil barang pesanan'],
        'packing' => ['label' => 'Packing', 'keterangan' => 'Verifikasi lalu kirim'],
        'transfer' => ['label' => 'Transfer', 'keterangan' => 'Pindah antar lokasi'],
        'opname' => ['label' => 'Stock Opname', 'keterangan' => 'Hitung stok per lokasi'],
    ];

    public function menu(): View
    {
        return view('scan.menu', ['modes' => self::MODES]);
    }

    public function show(string $mode): View
    {
        return view("scan.{$mode}");
    }
}
