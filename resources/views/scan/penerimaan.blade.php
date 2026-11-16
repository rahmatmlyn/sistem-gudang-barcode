@extends('scan.layout')
@section('title', 'Penerimaan Barang')
@section('content')
<div x-data="penerimaan" @scan.window="onScan($event.detail)" class="flex grow flex-col">
    <x-scan.header title="Penerimaan Barang">
        <x-slot:sub><span x-text="doc ? doc.nomor + ' · ' + doc.status : 'Pilih atau buat penerimaan'"></span></x-slot:sub>
    </x-scan.header>

    <main class="flex grow flex-col gap-3 p-4">
        <x-scan.input :label="'Scan barcode barang'" />
        <x-scan.card />

        <x-scan.doc-list kosong="Belum ada penerimaan terbuka.">
            <form @submit.prevent="buat()" class="grid grid-cols-2 gap-2 rounded-xl border border-garis bg-white p-3">
                <input x-model="baru.partner" placeholder="Nama supplier" class="col-span-2 min-h-11 rounded-lg border border-garis px-3">
                <input x-model="baru.reference" placeholder="No. referensi PO" class="min-h-11 rounded-lg border border-garis px-3">
                <button type="submit" class="min-h-11 rounded-lg bg-aksen font-semibold text-white">Penerimaan baru</button>
            </form>
        </x-scan.doc-list>

        <template x-if="doc">
            <section class="flex flex-col gap-3">
                <div class="grid grid-cols-2 gap-2 rounded-xl border border-garis bg-white px-3.5 py-3">
                    <div><div class="text-xs text-redup">Supplier</div><div class="text-sm font-medium" x-text="doc.mitra || '-'"></div></div>
                    <div><div class="text-xs text-redup">No. referensi PO</div><div class="text-sm font-medium" x-text="doc.referensi || '-'"></div></div>
                </div>
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Barang diterima</h2>
                    <span class="text-sm text-redup" x-text="doc.baris.length + ' item'"></span>
                </div>
                <template x-for="baris in doc.baris" :key="baris.id">
                    <div class="flex items-center gap-3 rounded-xl border border-garis bg-white px-3.5 py-2.5">
                        <div class="flex min-w-0 grow flex-col">
                            <span class="truncate text-base font-semibold" x-text="baris.nama"></span>
                            <span class="text-[13px] text-redup" x-text="'SKU ' + baris.sku + ' · ' + baris.satuan + (baris.qty_rencana ? ' · rencana ' + baris.qty_rencana : '')"></span>
                        </div>
                        <button type="button" @click="ubah(baris, -1)" aria-label="Kurangi" class="size-11 rounded-[10px] border border-garis text-xl">−</button>
                        <span class="w-12 text-center text-lg font-bold" x-text="baris.qty_scan"></span>
                        <button type="button" @click="ubah(baris, 1)" aria-label="Tambah" class="size-11 rounded-[10px] border border-garis text-xl">+</button>
                    </div>
                </template>
                <div class="flex justify-between rounded-xl bg-white px-3.5 py-3 font-semibold">
                    <span>Total</span><span x-text="perBarang().reduce((total, row) => total + row.scan, 0) + ' (satuan dasar)'"></span>
                </div>
            </section>
        </template>
        <x-scan.camera />
    </main>

    <footer x-show="doc" class="sticky bottom-0 grid grid-cols-2 gap-2 border-t border-garis bg-white p-4">
        <button type="button" @click="closeDoc()" class="min-h-12 rounded-xl border border-garis bg-white text-base font-semibold">Simpan Draft</button>
        <button type="button" @click="selesai()" class="min-h-12 rounded-xl bg-aksen text-base font-semibold text-white">Selesai, lanjut Putaway</button>
    </footer>
</div>
@endsection
