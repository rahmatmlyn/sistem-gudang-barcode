<?php

namespace App\Http\Controllers\Api;

use App\Http\Presenters\ScanDocumentPresenter;
use App\Models\Document;
use App\Services\BarcodeResolver;
use App\Services\OpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/scan/opname/{document}/locations/{location} {"kosong": bool}: "Simpan, lokasi berikutnya". */
class OpnameLocationController
{
    use RespondsWithDomainErrors;

    public function __invoke(Request $request, Document $document, string $location, BarcodeResolver $resolver, OpnameService $opname): JsonResponse
    {
        $kosong = (bool) ($request->validate(['kosong' => ['sometimes', 'boolean']])['kosong'] ?? false);
        $target = $resolver->location($location, $document->warehouse_id);

        return $this->rescue(function () use ($opname, $document, $target, $request, $kosong) {
            $opname->finishLocation($document, $target, $request->user(), $kosong);

            return $this->ok(['data' => ScanDocumentPresenter::detail($document->refresh())]);
        });
    }
}
