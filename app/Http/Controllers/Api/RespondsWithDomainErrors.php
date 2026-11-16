<?php

namespace App\Http\Controllers\Api;

use Closure;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

trait RespondsWithDomainErrors
{
    /** Penolakan aksi dari layanan domain (InvalidArgumentException) → 422 JSON dengan kode AKSI_DITOLAK. */
    private function rescue(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $exception) {
            return response()->json(['status' => 'gagal', 'kode' => 'AKSI_DITOLAK', 'pesan' => $exception->getMessage()], 422);
        }
    }

    private function ok(array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'ok', ...$extra], $status);
    }
}
