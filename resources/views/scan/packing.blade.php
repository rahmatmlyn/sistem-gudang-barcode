@extends('scan.layout')
@section('title', 'Packing')
@section('content')
<div x-data="packing" @scan.window="onScan($event.detail)" class="flex grow flex-col">
    <x-scan.header title="Packing">
        <x-slot:sub><span x-text="doc ? doc.nomor + (doc.mitra ? ' · ' + doc.mitra : '') : 'Pilih pesanan'"></span></x-slot:sub>
    </x-scan.header>

    <main class="flex grow flex-col gap-3 p-4">
        <x-scan.input label="Scan setiap barang yang dikemas" />
        <x-scan.card />
        <x-scan.doc-list kosong="Tidak ada pesanan yang sedang diproses." />

        <template x-if="terkirim">
            <div class="flex flex-col gap-3 rounded-[14px] border-2 border-sukses-garis bg-sukses-muda p-4">
                <div class="text-lg font-semibold text-sukses" x-text="terkirim + ' terkirim'"></div>
                <a :href="'/api/scan/documents/' + encodeURIComponent(terkirim) + '/surat-jalan'" target="_blank"
                   class="flex min-h-12 items-center justify-center rounded-xl bg-aksen font-semibold text-white">Unduh surat jalan</a>
            </div>
        </template>

        <template x-if="doc && !terkirim">
            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-semibold">Verifikasi packing</h2>
                <template x-for="row in perBarang()" :key="row.sku">
                    <div class="flex items-center justify-between gap-3 rounded-xl border bg-white px-3.5 py-2.5"
                         :class="row.pack === row.rencana && row.scan === row.rencana ? 'border-sukses-garis' : 'border-garis'">
                        <div class="flex min-w-0 flex-col">
                            <span class="truncate font-semibold" x-text="row.nama"></span>
                            <span class="text-[13px] text-redup" x-text="'Pesan ' + row.rencana + ' · diambil ' + row.scan"></span>
                        </div>
                        <span class="text-lg font-bold" x-text="row.pack + ' / ' + row.rencana"></span>
                    </div>
                </template>
                <p x-show="!siapKirim()" class="text-sm text-redup">Kirim aktif bila qty pesanan, picking, dan packing sama. Bila tidak cocok, kembali ke picking.</p>
            </section>
        </template>
        <x-scan.camera />
    </main>

    <footer x-show="doc && !terkirim" class="sticky bottom-0 grid grid-cols-2 gap-2 border-t border-garis bg-white p-4">
        <a :href="doc ? '/scan/picking?dokumen=' + encodeURIComponent(doc.nomor) : '#'"
           class="flex min-h-12 items-center justify-center rounded-xl border border-garis bg-white font-semibold">Kembali ke picking</a>
        <button type="button" :disabled="!siapKirim()" @click="kirim()"
                class="min-h-12 rounded-xl bg-aksen font-semibold text-white disabled:opacity-40">Kirim</button>
    </footer>
</div>
@endsection
