<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya token Sanctum dengan ability eksplisit "uji-scan" (dibuat gudang:siapkan-uji-scan, yang menolak jalan di
 * production). Sesi halaman /scan dan token wildcard ditolak agar staf tidak bisa melihat stok saat opname hitung buta.
 */
class EnsureTokenUjiScan
{
    public const ABILITY = 'uji-scan';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! $token->can(self::ABILITY) || $token->can('*')) {
            return response()->json(['status' => 'gagal', 'kode' => 'AKSES_DITOLAK', 'pesan' => 'Endpoint stok hanya untuk token uji scan.'], 403);
        }

        return $next($request);
    }
}
