<?php

namespace App\Http\Controllers\Api;

use App\Enums\ScanMode;
use App\Http\Requests\ScanRequest;
use App\Services\Scan\ScanService;
use Illuminate\Http\JsonResponse;

/** POST /api/scan — sukses 200, stok kurang 409, aturan lain 422, barcode/dokumen tak dikenal 404. */
class ScanController
{
    public function __invoke(ScanRequest $request, ScanService $scans): JsonResponse
    {
        [$status, $body] = $scans->handle(
            ScanMode::from($request->string('type')->toString()),
            $request->string('document_no')->toString(),
            $request->string('barcode')->toString(),
            $request->input('location'),
            $request->input('to_location'),
            (int) ($request->input('qty') ?? 1),
            $request->user(),
            $request->input('client_scan_id'),
        );

        return response()->json($body, $status);
    }
}
