<?php

namespace App\Imports\Dummy;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Imports\SpreadsheetReader;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impor stock_movements.csv dummy menjadi dokumen selesai + baris + ledger (M10). Pengecualian tunggal
 * aturan "hanya StockService": insert massal dalam satu transaksi pemanggil, lalu diverifikasi gudang:verifikasi-saldo.
 * Dokumen = pasangan (document_no, type); mutasi PUT bernomor GRN menjadi dokumen PUT baru yang terkait GRN itu.
 * Baris = agregat per dokumen–barang–asal–tujuan; created_at tiap mutasi dipertahankan.
 */
class MovementImporter
{
    private const CHUNK = 1000;

    /** @var array<string, int> */
    private array $items;

    /** @var array<string, int> */
    private array $locations;

    /** @var array<string, int> */
    private array $users;

    public function __construct()
    {
        $this->items = DB::table('items')->pluck('id', 'sku')->all();
        $this->locations = DB::table('locations')->pluck('id', 'code')->all();
        $this->users = DB::table('users')->pluck('id', 'username')->all();
    }

    /** @return array{dokumen: int, baris: int, mutasi: int} */
    public function import(string $path, int $warehouseId): array
    {
        [$documents, $lines] = $this->collect($path);
        $documentIds = $this->insertDocuments($documents, $warehouseId);
        $lineIds = $this->insertLines($lines, $documentIds);

        $movements = 0;
        $batch = [];
        foreach ($this->rows($path) as $row) {
            $batch[] = [
                'item_id' => $row['item'], 'from_location_id' => $row['from'], 'to_location_id' => $row['to'], 'qty' => $row['qty'],
                'type' => $row['type'], 'document_line_id' => $lineIds[$row['line']], 'user_id' => $row['user'], 'created_at' => $row['at'],
            ];
            if (count($batch) === self::CHUNK) {
                $movements += $this->flush('stock_movements', $batch);
            }
        }
        $movements += $this->flush('stock_movements', $batch);
        $this->updateSequences(DB::table('documents')->whereIn('id', array_values($documentIds))->pluck('number')->all());

        return ['dokumen' => count($documentIds), 'baris' => count($lineIds), 'mutasi' => $movements];
    }

    /** @return array{0: array<string, array>, 1: array<string, int>} dokumen per kunci dan qty per kunci baris */
    private function collect(string $path): array
    {
        $documents = [];
        $lines = [];
        foreach ($this->rows($path) as $row) {
            $document = &$documents[$row['doc']];
            $document ??= ['source' => $row['source'], 'type' => $row['type'], 'first_at' => $row['at'], 'last_at' => $row['at'],
                'first_user' => $row['user'], 'last_user' => $row['user']];
            if ($row['at'] < $document['first_at']) {
                [$document['first_at'], $document['first_user']] = [$row['at'], $row['user']];
            }
            if ($row['at'] >= $document['last_at']) {
                [$document['last_at'], $document['last_user']] = [$row['at'], $row['user']];
            }
            unset($document);
            $lines[$row['line']] = ($lines[$row['line']] ?? 0) + $row['qty'];
        }
        uasort($documents, fn (array $a, array $b) => [$a['first_at'], $a['source']] <=> [$b['first_at'], $b['source']]);

        return [$documents, $lines];
    }

    /** @return array<string, int> nomor dokumen → id, untuk kunci dokumen */
    private function insertDocuments(array $documents, int $warehouseId): array
    {
        $numbers = [];
        $next = [];
        foreach ($documents as $key => $document) {
            $native = str_starts_with($document['source'], $document['type'].'-');
            $year = substr($document['first_at'], 0, 4);
            $counter = $document['type'].'-'.$year;
            $next[$counter] ??= (int) DB::table('document_sequences')->where('type', $document['type'])->where('year', (int) $year)->value('last_number');
            $numbers[$key] = $native ? $document['source']
                : sprintf('%s-%s-%05d', $document['type'], $year, ++$next[$counter]);
        }

        $batch = [];
        foreach ($documents as $key => $document) {
            $isAdjustment = $document['type'] === DocumentType::ADJ->value;
            $batch[] = [
                'number' => $numbers[$key], 'type' => $document['type'], 'status' => DocumentStatus::Selesai->value,
                'warehouse_id' => $warehouseId, 'reference' => $numbers[$key] === $document['source'] ? null : $document['source'],
                'notes' => 'Import data dummy', 'created_by' => $document['first_user'], 'completed_by' => $document['last_user'],
                'completed_at' => $document['last_at'], 'created_at' => $document['first_at'], 'updated_at' => $document['last_at'],
                'approved_by' => $isAdjustment ? $document['last_user'] : null, 'approved_at' => $isAdjustment ? $document['last_at'] : null,
                'approval_reason' => $isAdjustment ? 'Data awal (import dummy)' : null,
            ];
            if (count($batch) === self::CHUNK) {
                $this->flush('documents', $batch);
            }
        }
        $this->flush('documents', $batch);

        $ids = DB::table('documents')->whereIn('number', array_values($numbers))->pluck('id', 'number')->all();
        foreach ($documents as $key => $document) {
            if ($numbers[$key] !== $document['source'] && isset($ids[$document['source']])) {
                DB::table('documents')->where('id', $ids[$numbers[$key]])->update(['related_document_id' => $ids[$document['source']]]);
            }
        }

        return array_map(fn (string $number) => $ids[$number], $numbers);
    }

    /** @return array<string, int> kunci baris → id */
    private function insertLines(array $lines, array $documentIds): array
    {
        $batch = [];
        foreach ($lines as $key => $qty) {
            [$documentKey, $item, $from, $to] = explode('#', $key);
            $batch[] = ['document_id' => $documentIds[$documentKey], 'item_id' => (int) $item, 'from_location_id' => $from === '' ? null : (int) $from,
                'to_location_id' => $to === '' ? null : (int) $to, 'qty_planned' => $qty, 'qty_scanned' => $qty,
                'qty_packed' => 0, 'created_at' => now(), 'updated_at' => now()];
            if (count($batch) === self::CHUNK) {
                $this->flush('document_lines', $batch);
            }
        }
        $this->flush('document_lines', $batch);

        $keyByDocument = array_flip($documentIds);
        $ids = [];
        DB::table('document_lines')->whereIn('document_id', array_values($documentIds))->orderBy('id')
            ->select('id', 'document_id', 'item_id', 'from_location_id', 'to_location_id')
            ->chunkById(5000, function ($rows) use (&$ids, $keyByDocument) {
                foreach ($rows as $row) {
                    $ids[implode('#', [$keyByDocument[$row->document_id], $row->item_id, $row->from_location_id, $row->to_location_id])] = $row->id;
                }
            });

        return $ids;
    }

    /** Lanjutkan penomoran setelah nomor terbesar per tipe dan tahun (aturan 6). */
    private function updateSequences(array $numbers): void
    {
        $max = [];
        foreach ($numbers as $number) {
            if (preg_match('/^([A-Z]+)-(\d{4})-(\d+)$/', $number, $match)) {
                $max["{$match[1]}|{$match[2]}"] = max($max["{$match[1]}|{$match[2]}"] ?? 0, (int) $match[3]);
            }
        }
        foreach ($max as $key => $last) {
            [$type, $year] = explode('|', $key);
            DB::table('document_sequences')->upsert(
                [['type' => $type, 'year' => (int) $year, 'last_number' => $last]],
                ['type', 'year'], ['last_number'],
            );
        }
    }

    /** @return \Generator<int, array{doc: string, source: string, type: string, item: int, from: ?int, to: ?int, qty: int, user: ?int, at: string, line: string}> */
    private function rows(string $path): \Generator
    {
        foreach (SpreadsheetReader::rows($path) as $number => $row) {
            if ($number === 1) {
                continue;
            }
            $item = $this->items[$row['sku']] ?? throw new RuntimeException("Baris {$number}: SKU {$row['sku']} tidak dikenal.");
            $from = $this->location($row['from_location'] ?? null, $number);
            $to = $this->location($row['to_location'] ?? null, $number);
            $doc = "{$row['document_no']}|{$row['type']}";

            yield ['doc' => $doc, 'source' => $row['document_no'], 'type' => DocumentType::from($row['type'])->value, 'item' => $item,
                'from' => $from, 'to' => $to, 'qty' => (int) $row['qty'], 'user' => $this->users[$row['user'] ?? ''] ?? null,
                'at' => $row['created_at'], 'line' => implode('#', [$doc, $item, $from, $to])];
        }
    }

    private function location(?string $code, int $number): ?int
    {
        return $code === null ? null : ($this->locations[$code] ?? throw new RuntimeException("Baris {$number}: lokasi {$code} tidak dikenal."));
    }

    private function flush(string $table, array &$batch): int
    {
        $count = count($batch);
        if ($count > 0) {
            DB::table($table)->insert($batch);
        }
        $batch = [];

        return $count;
    }
}
