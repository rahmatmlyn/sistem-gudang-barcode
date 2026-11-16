<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LaporanImportController;
use App\Http\Controllers\ScanPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('role:staf,supervisor,admin')->group(function () {
        Route::get('/scan', [ScanPageController::class, 'menu'])->name('scan');
        Route::get('/scan/{mode}', [ScanPageController::class, 'show'])
            ->whereIn('mode', array_keys(ScanPageController::MODES))
            ->name('scan.mode');
    });

    Route::get('/laporan-import/{laporan}', LaporanImportController::class)
        ->whereUuid('laporan')
        ->middleware('role:admin')
        ->name('laporan-import.unduh');
});
