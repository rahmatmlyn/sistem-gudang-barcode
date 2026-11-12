<?php

namespace App\Services\Stock;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\User;
use App\Services\DocumentNumberService;

/**
 * Membuat dokumen ADJ berstatus selesai beserta barisnya; movement ditulis StockService lewat LedgerWriter.
 */
class AdjustmentDocumentBuilder
{
    public function __construct(private DocumentNumberService $numbers) {}

    /**
     * @param  list<array{item_id: int, from: int|null, to: int|null, qty: int}>  $lines
     * @return array{0: Document, 1: list<PlannedMovement>}
     */
    public function build(Document $source, User $approver, string $reason, array $lines, array $attributes = []): array
    {
        $now = now();
        $adjustment = Document::create([
            'number' => $this->numbers->next(DocumentType::ADJ),
            'type' => DocumentType::ADJ,
            'status' => DocumentStatus::Selesai,
            'warehouse_id' => $source->warehouse_id,
            'reference' => $source->number,
            'created_by' => $approver->id,
            'completed_by' => $approver->id,
            'completed_at' => $now,
            'approved_by' => $approver->id,
            'approved_at' => $now,
            'approval_reason' => $reason,
            ...$attributes,
        ]);

        $movements = [];
        foreach ($lines as $line) {
            $documentLine = DocumentLine::create([
                'document_id' => $adjustment->id,
                'item_id' => $line['item_id'],
                'from_location_id' => $line['from'],
                'to_location_id' => $line['to'],
                'qty_planned' => $line['qty'],
                'qty_scanned' => $line['qty'],
            ]);

            $movements[] = new PlannedMovement($line['item_id'], $line['from'], $line['to'], $line['qty'], $documentLine->id);
        }

        return [$adjustment, $movements];
    }
}
