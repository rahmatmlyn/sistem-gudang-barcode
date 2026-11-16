@extends('scan.layout')
@section('title', 'Picking')
@section('content')
<div x-data="picking" @scan.window="onScan($event.detail)" class="flex grow flex-col">
    <x-scan.header title="Picking">
        <x-slot:sub><span x-text="doc ? doc.nomor + (doc.mitra ? ' · ' + doc.mitra : '') : 'Pilih pesanan'"></span></x-slot:sub>
        <span x-show="doc" class="text-sm font-semibold" x-text="doc ? selesaiBaris() + ' / ' + doc.baris.length : ''"></span>
        <x-slot:progress>
            <div x-show="doc" class="h-2 overflow-hidden rounded-full bg-[#E4E7EC]">
                <div class="h-2 bg-aksen" :style="doc && doc.baris.length ? `width: ${selesaiBaris() / doc.baris.length * 100}%` : 'width:0'"></div>
            </div>
        </x-slot:progress>
    </x-scan.header>

    <main class="flex grow flex-col gap-3 p-4">
        <template x-if="doc && tugas()">
            <div class="flex flex-col gap-3 rounded-[14px] border-2 border-aksen bg-white p-4">
                <div class="text-xs font-semibold tracking-wider text-redup">AMBIL DARI LOKASI</div>
                <div class="text-[40px] font-bold leading-none tracking-wide" x-text="tugas().lokasi_asal"></div>
                <div class="h-px bg-[#E4E7EC]"></div>
                <div class="flex items-end justify-between gap-3">
                    <div class="flex min-w-0 flex-col">
                        <span class="text-base font-semibold" x-text="tugas().nama"></span>
                        <span class="text-[13px] text-redup" x-text="'SKU ' + tugas().sku"></span>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="text-xs text-redup">Ambil</span>
                        <span class="text-[22px] font-bold" x-text="sisa(tugas()) + ' ' + tugas().satuan"></span>
                    </div>
                </div>
                <div class="flex gap-2 text-[13px] font-semibold">
                    <span class="grow rounded-[10px] px-3 py-2.5" :class="lokasiBenar() ? 'bg-sukses-muda text-sukses' : 'bg-[#EEF3FC] text-aksen'"
                          x-text="lokasiBenar() ? '1. Lokasi discan' : '1. Scan lokasi'"></span>
                    <span class="grow rounded-[10px] px-3 py-2.5" :class="lokasiBenar() ? 'bg-[#EEF3FC] text-aksen' : 'bg-latar text-redup'"
                          x-text="'2. Scan barang (' + tugas().qty_scan + '/' + tugas().qty_rencana + ')'"></span>
                </div>
            </div>
        </template>
        <template x-if="doc && !tugas()">
            <div class="flex flex-col gap-3 rounded-[14px] border-2 border-sukses-garis bg-sukses-muda p-4">
                <div class="text-lg font-semibold text-sukses">Semua barang sudah diambil</div>
                <a :href="'/scan/packing?dokumen=' + encodeURIComponent(doc.nomor)"
                   class="flex min-h-12 items-center justify-center rounded-xl bg-aksen font-semibold text-white">Lanjut packing</a>
            </div>
        </template>

        <x-scan.input label="Scan barcode lokasi atau barang" />
        <x-scan.card />
        <x-scan.doc-list kosong="Tidak ada pesanan yang dirilis ke picking." />

        <template x-if="doc && berikutnya().length">
            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-semibold">Berikutnya</h2>
                <template x-for="baris in berikutnya()" :key="baris.id">
                    <div class="flex items-center gap-3 rounded-xl border border-garis bg-white px-3.5 py-2.5">
                        <span class="w-24 font-bold" x-text="baris.lokasi_asal"></span>
                        <span class="grow truncate" x-text="baris.nama"></span>
                        <span class="font-semibold" x-text="sisa(baris) + ' ' + baris.satuan"></span>
                    </div>
                </template>
            </section>
        </template>
        <x-scan.camera />
    </main>

    <footer x-show="doc && tugas()" class="sticky bottom-0 grid grid-cols-2 gap-2 border-t border-garis bg-white p-4">
        <button type="button" @click="kosong()" class="min-h-12 rounded-xl border border-gagal-garis bg-white font-semibold text-gagal">Laporkan kosong</button>
        <button type="button" @click="closeDoc()" class="min-h-12 rounded-xl border border-garis bg-white font-semibold">Pesanan lain</button>
    </footer>
</div>
@endsection
