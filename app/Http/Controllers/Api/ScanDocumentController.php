<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Presenters\ScanDocumentPresenter;
use App\Models\Document;
use App\Services\Scan\ScanDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Dokumen untuk halaman /scan. Penolakan aturan stok dirender sebagai JSON {status, kode, pesan} (bootstrap/app.php). */
class ScanDocumentController
{
    use RespondsWithDomainErrors;

    public function __construct(private ScanDocumentService $documents) {}

    public function index(Request $request): JsonResponse
    {
        $type = DocumentType::from($request->validate(['type' => ['required', Rule::enum(DocumentType::class)]])['type']);

        $documents = Document::query()->with('lines')->where('type', $type)
            ->when($type === DocumentType::OUT, fn ($query) => $query->where('status', DocumentStatus::Proses), fn ($query) => $query->open())
            ->when($type === DocumentType::OPN, fn ($query) => $query->whereNull('submitted_at'))
            ->latest('id')->limit(50)->get();

        return response()->json(['data' => $documents->map(ScanDocumentPresenter::summary(...))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([DocumentType::GRN->value, DocumentType::TRF->value])],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'partner' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $document = $this->documents->create(DocumentType::from($data['type']), $request->user(), $data);

        return response()->json(['data' => ScanDocumentPresenter::detail($document)], 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json(['data' => ScanDocumentPresenter::detail($document)]);
    }

    public function complete(Request $request, Document $document): JsonResponse
    {
        return $this->rescue(fn () => $this->ok([
            'data' => ScanDocumentPresenter::detail($this->documents->complete($document, $request->user())),
        ]));
    }

    public function putaway(Request $request, Document $document): JsonResponse
    {
        $put = $this->documents->createPutaway($document, $request->user());

        return response()->json(['data' => ScanDocumentPresenter::detail($put)], 201);
    }

    public function suratJalan(Document $document): Response
    {
        abort_unless($document->type === DocumentType::OUT, 404);
        $document->load(['lines.item', 'warehouse', 'completer']);

        return Pdf::loadView('pdf.surat-jalan', ['document' => $document])
            ->setPaper('a4')
            ->stream("surat-jalan-{$document->number}.pdf");
    }
}
