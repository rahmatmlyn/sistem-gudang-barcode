@extends('scan.layout')
@section('title', 'Stock Opname')
@section('content')
<div x-data="opname" @scan.window="onScan($event.detail)" class="flex grow flex-col">
    <x-scan.header title="Stock Opname">
        <x-slot:sub><span x-text="doc ? doc.nomor : 'Pilih dokumen opname'"></span></x-slot:sub>
        <span x-show="doc" class="text-sm font-semibold" x-text="doc ? 'Lokasi ' + lokasiSelesai() + ' / ' + doc.lokasi.length : ''"></span>
    </x-scan.header>

    <main class="flex grow flex-col gap-3 p-4">
        <template x-if="doc">
            <div class="flex items-center justify-between rounded-[14px] border-2 border-aksen bg-white p-4">
                <div class="flex flex-col">
                    <span class="text-xs font-semibold tracking-wider text-redup">LOKASI AKTIF</span>
                    <span class="text-[32px] font-bold leading-tight" x-text="lokasi ?? 'Scan lokasi'"></span>
                </div>
                <button type="button" x-show="lokasi" @click="lokasi = null; refocus()" class="min-h-11 rounded-[10px] border border-garis px-3 text-sm font-semibold">Ganti lokasi</button>
            </div>
        </template>
        <x-scan.input label="Scan setiap barang di lokasi ini" />
        <p x-show="doc" class="rounded-xl bg-[#EEF3FC] px-3.5 py-2.5 text-sm text-aksen-tua">
            Hitung buta: jumlah di sistem tidak ditampilkan. Supervisor akan membandingkan hasil hitung di web.
        </p>
        <x-scan.card />
        <x-scan.doc-list kosong="Tidak ada opname yang perlu dihitung." />

        <template x-if="doc && lokasi">
            <section class="flex flex-col gap-2">
                <div class="flex justify-between"><h2 class="text-sm font-semibold">Hasil hitung</h2><span class="text-sm text-redup" x-text="barisLokasi().length + ' barang'"></span></div>
                <template x-for="baris in barisLokasi()" :key="baris.id">
                    <div class="flex items-center gap-3 rounded-xl border border-garis bg-white px-3.5 py-2.5">
                        <div class="flex min-w-0 grow flex-col">
                            <span class="truncate font-semibold" x-text="baris.nama"></span>
                            <span class="text-[13px] text-redup" x-text="'SKU ' + baris.sku + ' · ' + baris.satuan"></span>
                        </div>
                        <button type="button" @click="ubah(baris, -1)" aria-label="Kurangi" class="size-11 rounded-[10px] border border-garis text-xl">−</button>
                        <span class="w-12 text-center text-lg font-bold" x-text="baris.qty_scan"></span>
                        <button type="button" @click="ubah(baris, 1)" aria-label="Tambah" class="size-11 rounded-[10px] border border-garis text-xl">+</button>
                    </div>
                </template>
            </section>
        </template>
        <template x-if="doc && !lokasi">
            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-semibold" x-text="lokasiBelum().length ? 'Lokasi belum dihitung' : 'Semua lokasi sudah dihitung'"></h2>
                <div class="flex flex-wrap gap-2">
                    <template x-for="row in lokasiBelum()" :key="row.kode">
                        <span class="rounded-lg border border-garis bg-white px-3 py-2 font-semibold" x-text="row.kode"></span>
                    </template>
                </div>
                <button type="button" x-show="!lokasiBelum().length" @click="ajukan()" class="min-h-12 rounded-xl bg-aksen font-semibold text-white">Ajukan ke supervisor</button>
            </section>
        </template>
        <x-scan.camera />
    </main>

    <footer x-show="doc && lokasi" class="sticky bottom-0 grid grid-cols-3 gap-2 border-t border-garis bg-white p-4">
        <button type="button" @click="simpanLokasi(true)" class="min-h-12 rounded-xl border border-garis bg-white text-sm font-semibold">Lokasi ini kosong</button>
        <a href="/scan" class="flex min-h-12 items-center justify-center rounded-xl border border-garis bg-white text-sm font-semibold">Jeda</a>
        <button type="button" @click="simpanLokasi(false)" class="min-h-12 rounded-xl bg-aksen text-sm font-semibold text-white">Simpan, lokasi berikutnya</button>
    </footer>
</div>
@endsection
