<?php

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Services\DocumentNumberService;
use Illuminate\Support\Carbon;

it('membuat GRN-2026-00001 lalu GRN-2026-00002', function () {
    $service = app(DocumentNumberService::class);
    $waktu = Carbon::parse('2026-03-01');

    expect($service->next(DocumentType::GRN, $waktu))->toBe('GRN-2026-00001');
    expect($service->next(DocumentType::GRN, $waktu))->toBe('GRN-2026-00002');
});

it('urutan terpisah per tipe dan per tahun', function () {
    $service = app(DocumentNumberService::class);

    $service->next(DocumentType::GRN, Carbon::parse('2026-01-05'));

    expect($service->next(DocumentType::OUT, Carbon::parse('2026-01-05')))->toBe('OUT-2026-00001');
    expect($service->next(DocumentType::GRN, Carbon::parse('2027-01-02')))->toBe('GRN-2027-00001');
    expect($service->next(DocumentType::GRN, Carbon::parse('2026-12-31')))->toBe('GRN-2026-00002');
});

it('memakai tahun saat ini bila waktu tidak diberikan', function () {
    Carbon::setTestNow('2028-06-15 10:00:00');

    expect(app(DocumentNumberService::class)->next(DocumentType::TRF))->toBe('TRF-2028-00001');

    Carbon::setTestNow();
});

it('melanjutkan dari urutan yang sudah ada (mis. setelah import data)', function () {
    DocumentSequence::create(['type' => 'OPN', 'year' => 2026, 'last_number' => 941]);

    expect(app(DocumentNumberService::class)->next(DocumentType::OPN, Carbon::parse('2026-07-01')))
        ->toBe('OPN-2026-00942');
});
