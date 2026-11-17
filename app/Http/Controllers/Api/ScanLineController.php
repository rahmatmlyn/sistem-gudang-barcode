<?php

namespace App\Http\Controllers\Api;

use App\Models\DocumentLine;
use App\Services\Scan\ScanDocumentService;
use App\Services\Scan\ScanResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** PATCH /api/scan/lines/{line}: {"delta": ±n} untuk tombol − / +, atau {"kosong": true} untuk "Laporkan kosong". */
class ScanLineController
{
    use RespondsWithDomainErrors;

    public function __invoke(Request $request, DocumentLine $line, ScanDocumentService $documents): JsonResponse
    {
        $data = $request->validate([
            'delta' => ['exclude_if:kosong,true', 'required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'kosong' => ['sometimes', 'boolean'],
        ]);

        return $this->rescue(function () use ($data, $line, $documents, $request) {
            $line = ($data['kosong'] ?? false)
                ? $documents->reportEmpty($line, $request->user())
                : $documents->adjustLine($line, (int) $data['delta'], $request->user());

            return $this->ok(['baris' => ScanResult::baris($line)]);
        });
    }
}
