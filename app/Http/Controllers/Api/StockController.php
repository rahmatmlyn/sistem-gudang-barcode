<?php

namespace App\Http\Controllers\Api;

use App\Models\StockBalance;
use App\Services\BarcodeResolver;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/stock?barcode=&location= → qty tersedia (saldo − dicadangkan scan dokumen terbuka), K1. Hanya token uji (EnsureTokenUjiScan). */
class StockController
{
    public function __invoke(Request $request, BarcodeResolver $resolver, StockService $stock): JsonResponse
    {
        $request->validate(['barcode' => ['required', 'string'], 'location' => ['required', 'string']]);
        $itemId = $resolver->item($request->string('barcode')->toString())->item_id;
        $locationId = $resolver->location($request->string('location')->toString())->id;

        $available = $stock->available($itemId, $locationId);
        $balance = (int) StockBalance::query()->where('item_id', $itemId)->where('location_id', $locationId)->value('qty');

        return response()->json(['qty' => $available, 'saldo' => $balance, 'dipesan' => $balance - $available]);
    }
}
