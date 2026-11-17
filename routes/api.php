<?php

use App\Http\Controllers\Api\OpnameLocationController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\ScanDocumentController;
use App\Http\Controllers\Api\ScanLineController;
use App\Http\Controllers\Api\StockController;
use App\Http\Middleware\IdempotentRequest;
use Illuminate\Support\Facades\Route;

/*
 * API halaman scan. Sesi (halaman /scan, Sanctum stateful) atau token Bearer (tools/uji_scan_bersamaan.py).
 */
Route::middleware(['auth:sanctum', 'role:staf,supervisor,admin'])->group(function () {
    Route::post('/scan', ScanController::class);
    Route::get('/stock', StockController::class);

    Route::prefix('scan')->middleware(IdempotentRequest::class)->group(function () {
        Route::get('/documents', [ScanDocumentController::class, 'index']);
        Route::post('/documents', [ScanDocumentController::class, 'store']);
        Route::get('/documents/{document:number}', [ScanDocumentController::class, 'show']);
        Route::post('/documents/{document:number}/complete', [ScanDocumentController::class, 'complete']);
        Route::post('/documents/{document:number}/putaway', [ScanDocumentController::class, 'putaway']);
        Route::get('/documents/{document:number}/surat-jalan', [ScanDocumentController::class, 'suratJalan']);
        Route::patch('/lines/{line}', ScanLineController::class);
        Route::post('/opname/{document:number}/locations/{location}', OpnameLocationController::class);
    });
});
