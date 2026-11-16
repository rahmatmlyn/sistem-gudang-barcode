@extends('scan.layout')
@section('title', 'Putaway')
@section('content')
<div x-data="putaway" @scan.window="onScan($event.detail)" class="flex grow flex-col">
    <x-scan.header title="Putaway">
        <x-slot:sub><span x-text="doc ? doc.nomor + (doc.referensi ? ' · dari ' + doc.referensi : '') : 'Pilih dokumen putaway'"></span></x-slot:sub>
    </x-scan.header>

    <main class="flex grow flex-col gap-3 p-4">
        <template x-if="doc">
            <div class="rounded-[14px] border-2 border-aksen bg-white p-4">
                <div class="text-xs font-semibold tracking-wider text-redup">SIMPAN KE LOKASI</div>
                <div class="text-[40px] font-bold leading-tight" x-text="lokasi ?? '—'"></div>
                <div class="text-sm text-redup" x-text="lokasi ? 'Scan barang yang disimpan di sini' : '1. Scan barcode rak/bin tujuan'"></div>
            </div>
        </template>
        <x-scan.input label="Scan lokasi rak, lalu barang" />
        <x-scan.card />
        <x-scan.doc-list kosong="Tidak ada putaway terbuka. Selesaikan penerimaan dulu." />

        <template x-if="doc">
            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-semibold">Barang dari area terima</h2>
                <template x-for="row in perBarang()" :key="row.sku">
                    <div class="flex items-center justify-between rounded-xl border border-garis bg-white px-3.5 py-2.5">
                        <div class="flex min-w-0 flex-col">
                            <span class="truncate font-semibold" x-text="row.nama"></span>
                            <span class="text-[13px] text-redup" x-text="'SKU ' + row.sku"></span>
                        </div>
                        <span class="text-lg font-bold" :class="row.scan >= row.rencana ? 'text-sukses' : ''" x-text="row.scan + ' / ' + row.rencana"></span>
                    </div>
                </template>
                <template x-for="baris in doc.baris.filter((baris) => baris.qty_scan > 0)" :key="baris.id">
                    <div class="flex items-center gap-3 rounded-xl bg-white px-3.5 py-2 text-sm">
                        <span class="grow" x-text="baris.nama + ' → ' + baris.lokasi_tujuan"></span>
                        <button type="button" @click="ubah(baris, -1)" aria-label="Kurangi" class="size-11 rounded-[10px] border border-garis text-xl">−</button>
                        <span class="w-10 text-center font-bold" x-text="baris.qty_scan"></span>
                    </div>
                </template>
            </section>
        </template>
        <x-scan.camera />
    </main>

    <footer x-show="doc" class="sticky bottom-0 grid grid-cols-2 gap-2 border-t border-garis bg-white p-4">
        <button type="button" @click="lokasi = null; refocus()" class="min-h-12 rounded-xl border border-garis bg-white font-semibold">Ganti lokasi</button>
        <button type="button" @click="selesai()" class="min-h-12 rounded-xl bg-aksen font-semibold text-white">Selesai</button>
    </footer>
</div>
@endsection
