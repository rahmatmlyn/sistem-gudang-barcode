@props(['kosong' => 'Belum ada dokumen.'])
<section x-show="!doc" class="flex flex-col gap-3">
    {{ $slot }}
    <h2 class="text-sm font-semibold text-redup">Pilih dokumen (atau scan nomor dokumen)</h2>
    <p x-show="loading" class="text-redup">Memuat…</p>
    <p x-show="!loading && docs.length === 0" class="rounded-xl border border-garis bg-white p-4 text-redup">{{ $kosong }}</p>
    <template x-for="item in docs" :key="item.nomor">
        <button type="button" @click="openDoc(item.nomor)"
                class="flex min-h-14 items-center justify-between gap-3 rounded-xl border border-garis bg-white px-4 py-3 text-left">
            <span class="flex flex-col">
                <span class="text-base font-semibold" x-text="item.nomor"></span>
                <span class="text-sm text-redup" x-text="[item.mitra, item.referensi].filter(Boolean).join(' · ') || item.status"></span>
            </span>
            <span class="text-sm font-semibold text-aksen" x-text="item.progres.scan + ' / ' + item.progres.rencana"></span>
        </button>
    </template>
</section>
