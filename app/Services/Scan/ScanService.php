<?php

namespace App\Services\Scan;

use App\Enums\ScanMode;
use App\Exceptions\DokumenTidakAktifException;
use App\Exceptions\DokumenTidakDikenalException;
use App\Exceptions\StockRuleException;
use App\Models\Document;
use App\Models\ItemBarcode;
use App\Models\ScanLog;
use App\Models\User;
use App\Services\BarcodeResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/scan. Satu scan = satu transaksi yang mengunci dokumen lebih dulu (urutan kunci: dokumen → saldo → baris).
 * Idempoten per client_scan_id (UAT-31): log scan ditulis di transaksi yang sama dengan perubahan qty, sehingga
 * kiriman ulang mengembalikan respons pertama tanpa menambah qty.
 */
class ScanService
{
    public function __construct(private BarcodeResolver $resolver) {}

    /** @return array{0: int, 1: array<string, mixed>} [status HTTP, body JSON] */
    public function handle(
        ScanMode $mode, string $documentNo, string $barcode, ?string $location, ?string $toLocation,
        int $qty, User $user, ?string $clientScanId,
    ): array {
        if ($clientScanId !== null && ($previous = $this->previous($clientScanId)) !== null) {
            return $previous;
        }

        $context = ['mode' => $mode, 'barcode' => BarcodeResolver::clean($barcode), 'user_id' => $user->id, 'client_scan_id' => $clientScanId];

        try {
            return DB::transaction(function () use ($mode, $documentNo, $barcode, $location, $toLocation, $qty, $user, &$context) {
                $document = $this->lockDocument($mode, $documentNo);
                $context['document_id'] = $document->id;
                $input = new ScanInput(
                    $mode, $document,
                    BarcodeResolver::clean($location) === '' ? null : $this->resolver->location($location, $document->warehouse_id),
                    BarcodeResolver::clean($toLocation) === '' ? null : $this->resolver->location($toLocation, $document->warehouse_id),
                    $qty, $user,
                );
                $context['location_id'] = $input->location?->id;

                $resolved = $this->resolver->resolve($barcode, $document->warehouse_id);
                $result = $resolved instanceof ItemBarcode
                    ? $this->handler($mode)->onItem($input, $resolved, $resolved->toBaseQty($qty))
                    : $this->handler($mode)->onLocation($input, $resolved);

                $this->log($context, 200, $result->toArray(), true);

                return [200, $result->toArray()];
            });
        } catch (StockRuleException $exception) {
            $body = ['status' => 'gagal', 'kode' => $exception->errorCode(), 'pesan' => $exception->getMessage()];
            $this->logFailure($context, $exception->httpStatus(), $body);

            return [$exception->httpStatus(), $body];
        } catch (UniqueConstraintViolationException $exception) {
            return $this->previous((string) $clientScanId) ?? throw $exception;
        }
    }

    private function lockDocument(ScanMode $mode, string $documentNo): Document
    {
        $number = BarcodeResolver::clean($documentNo);
        $document = Document::query()->where('number', $number)->lockForUpdate()->first()
            ?? throw new DokumenTidakDikenalException("Dokumen {$number} tidak ditemukan.");

        if ($document->type !== $mode->documentType()) {
            throw new DokumenTidakAktifException("{$number} bukan dokumen {$mode->getLabel()}.");
        }
        if (! $document->status->isOpen()) {
            throw new DokumenTidakAktifException("Dokumen {$number} sudah {$document->status->getLabel()}.");
        }

        return $document;
    }

    private function handler(ScanMode $mode): ScanHandler
    {
        return app(match ($mode) {
            ScanMode::GRN => ReceiveScanHandler::class,
            ScanMode::PUT, ScanMode::TRF => MoveScanHandler::class,
            ScanMode::OUT => PickScanHandler::class,
            ScanMode::PACK => PackScanHandler::class,
            ScanMode::OPN => OpnameScanHandler::class,
        });
    }

    /** @return array{0: int, 1: array<string, mixed>}|null */
    private function previous(string $clientScanId): ?array
    {
        $log = ScanLog::query()->where('client_scan_id', $clientScanId)->first();

        return $log === null ? null : [(int) ($log->response['_http'] ?? 200), collect($log->response)->except('_http')->all()];
    }

    private function log(array $context, int $status, array $body, bool $success): void
    {
        ScanLog::create([
            ...$context,
            'success' => $success,
            'code' => $body['kode'] ?? null,
            'message' => $body['pesan'] ?? null,
            'response' => [...$body, '_http' => $status],
        ]);
    }

    /** Scan gagal tetap dicatat (di luar transaksi yang dibatalkan); kiriman ulang yang bersamaan cukup diabaikan. */
    private function logFailure(array $context, int $status, array $body): void
    {
        try {
            DB::transaction(fn () => $this->log($context, $status, $body, false));
        } catch (UniqueConstraintViolationException) {
            // client_scan_id yang sama sudah tercatat oleh kiriman lain
        }
    }
}
