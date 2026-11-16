@extends('scan.layout')
@section('title', 'Transfer')
@section('content')
<div x-data="transfer" @scan.window="onScan($event.detail)" class="flex grow flex-col">
    <x-scan.header title="Transfer Antar Lokasi">
        <x-slot:sub><span x-text="doc ? doc.nomor + ' · ' + doc.status : 'Pilih atau buat transfer'"></span></x-slot:sub>
    </x-scan.header>

    <main class="flex grow flex-col gap-3 p-4">
        <template x-if="doc">
            <div class="grid grid-cols-2 gap-2">
                <div class="rounded-[14px] border-2 bg-white p-3" :class="asal ? 'border-sukses-garis' : 'border-aksen'">
                    <div class="text-xs font-semibold tracking-wider text-redup">1. DARI</div>
                    <div class="text-2xl font-bold" x-text="asal ?? 'Scan lokasi'"></div>
                </div>
                <div class="rounded-[14px] border-2 bg-white p-3" :class="tujuan ? 'border-sukses-garis' : (asal ? 'border-aksen' : 'border-garis')">
                    <div class="text-xs font-semibold tracking-wider text-redup">2. KE</div>
                    <div class="text-2xl font-bold" x-text="tujuan ?? 'Scan lokasi'"></div>
                </div>
            </div>
        </template>
        <x-scan.input label="Scan lokasi asal, lokasi tujuan, lalu barang" />
        <x-scan.card />
        <x-scan.doc-list kosong="Belum ada transfer terbuka.">
            <button type="button" @click="buat()" class="min-h-12 rounded-xl bg-aksen font-semibold text-white">Transfer baru</button>
        </x-scan.doc-list>

        <template x-if="doc">
            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-semibold">Barang dipindah</h2>
                <p x-show="doc.baris.length === 0" class="text-sm text-redup">Belum ada barang.</p>
                <template x-for="baris in doc.baris" :key="baris.id">
                    <div class="flex items-center gap-3 rounded-xl border border-garis bg-white px-3.5 py-2.5">
                        <div class="flex min-w-0 grow flex-col">
                            <span class="truncate font-semibold" x-text="baris.nama"></span>
                            <span class="text-[13px] text-redup" x-text="(baris.lokasi_asal ?? '?') + ' → ' + (baris.lokasi_tujuan ?? '?') + (baris.qty_rencana ? ' · rencana ' + baris.qty_rencana : '')"></span>
                        </div>
                        <button type="button" @click="ubah(baris, -1)" aria-label="Kurangi" class="size-11 rounded-[10px] border border-garis text-xl">−</button>
                        <span class="w-10 text-center text-lg font-bold" x-text="baris.qty_scan"></span>
                    </div>
                </template>
            </section>
        </template>
        <x-scan.camera />
    </main>

    <footer x-show="doc" class="sticky bottom-0 grid grid-cols-2 gap-2 border-t border-garis bg-white p-4">
        <button type="button" @click="gantiLokasi()" class="min-h-12 rounded-xl border border-garis bg-white font-semibold">Ganti lokasi</button>
        <button type="button" @click="selesai()" class="min-h-12 rounded-xl bg-aksen font-semibold text-white">Selesai</button>
    </footer>
</div>
@endsection
