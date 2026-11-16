<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Filament\Resources\StockOpnames\Pages\ViewStockOpname;
use App\Filament\Resources\StockOpnames\RelationManagers\HasilHitungRelationManager;
use App\Models\Document;
use Livewire\Livewire;
use Tests\Support\Gudang;

beforeEach(fn () => $this->g = Gudang::siapkan());

it('membuat opname dengan memilih zona mengisi lokasi turunannya', function () {
    sebagai(UserRole::Supervisor);
    $zona = $this->g->lokasi('C', LocationType::Zona);
    $bin = $this->g->lokasi('C-01-01', LocationType::Bin, $zona);

    Livewire::test(CreateStockOpname::class)
        ->fillForm(['warehouse_id' => $this->g->gudang->id, 'lokasi' => [$zona->id], 'notes' => 'Opname zona C'])
        ->call('create')
        ->assertHasNoFormErrors();

    $opn = Document::ofType(DocumentType::OPN)->sole();
    expect($opn->number)->toStartWith('OPN-')
        ->and($opn->opnameLocations()->pluck('location_id')->sort()->values()->all())->toBe([$zona->id, $bin->id]);
});

it('supervisor menyetujui selisih dengan alasan (UAT-16)', function () {
    $supervisor = sebagai(UserRole::Supervisor);
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $opn = $this->g->opnameDiajukan([[$barang, 8]]);

    Livewire::test(ViewStockOpname::class, ['record' => $opn->getRouteKey()])
        ->callAction('setujui', data: ['alasan' => 'Dua pcs rusak'])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $adj = Document::ofType(DocumentType::ADJ)->sole();
    expect($adj)->approved_by->toBe($supervisor->id)->approval_reason->toBe('Dua pcs rusak');
    expect(Gudang::saldo($barang, $this->g->binA))->toBe(8);
});

it('approval tanpa alasan ditolak di panel (UAT-17)', function () {
    sebagai(UserRole::Supervisor);
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $opn = $this->g->opnameDiajukan([[$barang, 8]]);

    Livewire::test(ViewStockOpname::class, ['record' => $opn->getRouteKey()])
        ->callAction('setujui', data: ['alasan' => ''])
        ->assertHasActionErrors(['alasan' => 'required']);

    expect($opn->fresh()->status)->toBe(DocumentStatus::Proses)
        ->and(Document::ofType(DocumentType::ADJ)->exists())->toBeFalse();
});

it('admin tidak melihat tombol setujui dan tolak', function () {
    sebagai(UserRole::Admin);
    $opn = $this->g->opnameDiajukan([]);

    Livewire::test(ViewStockOpname::class, ['record' => $opn->getRouteKey()])
        ->assertActionHidden('setujui')
        ->assertActionHidden('tolak');
});

it('supervisor menolak opname dengan alasan', function () {
    sebagai(UserRole::Supervisor);
    $opn = $this->g->opnameDiajukan([]);

    Livewire::test(ViewStockOpname::class, ['record' => $opn->getRouteKey()])
        ->callAction('tolak', data: ['alasan' => 'Hitung ulang']);

    expect($opn->fresh()->status)->toBe(DocumentStatus::Batal);
});

it('hasil hitung menampilkan fisik, sistem, dan selisih', function () {
    sebagai(UserRole::Supervisor);
    $barang = $this->g->barang();
    $this->g->stokAwal($barang, $this->g->binA, 10);
    $opn = $this->g->opnameDiajukan([[$barang, 8]]);
    $baris = $opn->lines()->sole();

    Livewire::test(HasilHitungRelationManager::class, ['ownerRecord' => $opn, 'pageClass' => ViewStockOpname::class])
        ->assertCanSeeTableRecords([$baris])
        ->assertTableColumnStateSet('qty_scanned', 8, $baris)
        ->assertTableColumnStateSet('qty_system', 10, $baris)
        ->assertTableColumnStateSet('selisih', -2, $baris);
});
