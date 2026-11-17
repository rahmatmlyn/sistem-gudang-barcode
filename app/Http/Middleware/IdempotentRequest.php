<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Header Idempotency-Key (UUID) pada POST/PATCH: kiriman ulang dengan kunci yang sama (per pengguna) mendapat respons
 * pertama tanpa menjalankan aksi lagi. Kunci dan aksi ditulis dalam satu transaksi, jadi tidak ada aksi tanpa kunci;
 * respons 5xx dibatalkan bersama aksinya sehingga boleh dicoba ulang. Kiriman bersamaan menunggu di indeks unik.
 */
class IdempotentRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || ! in_array($request->method(), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return $next($request);
        }
        if (! Str::isUuid($key)) {
            return response()->json(['message' => 'Header Idempotency-Key harus UUID.'], 422);
        }

        $userId = $request->user()->getKey();
        DB::beginTransaction();
        try {
            $inserted = DB::table('idempotency_keys')->insertOrIgnore([
                'user_id' => $userId, 'key' => $key, 'method' => $request->method(), 'path' => $request->path(), 'created_at' => now(),
            ]);
            if ($inserted === 0) {
                $stored = DB::table('idempotency_keys')->where('user_id', $userId)->where('key', $key)->first();
                DB::commit();

                return JsonResponse::fromJsonString($stored->body ?? '{}', $stored->status ?? 409);
            }

            $response = $next($request);
            if ($response->getStatusCode() >= 500) {
                DB::rollBack();

                return $response;
            }
            DB::table('idempotency_keys')->where('user_id', $userId)->where('key', $key)
                ->update(['status' => $response->getStatusCode(), 'body' => $response->getContent() ?: '{}']);
            DB::commit();

            return $response;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
}
