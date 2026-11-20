<div x-data="cameraScanner()">
    <button type="button" @click="start()"
            class="min-h-11 w-full rounded-xl border border-garis bg-white px-4 text-base font-semibold text-aksen">
        Scan pakai kamera
    </button>
    <div x-cloak x-show="open" class="fixed inset-0 z-40 flex flex-col items-center justify-center gap-3 bg-black/95 p-4">
        <div id="kamera-scan" class="w-full max-w-sm overflow-hidden rounded-xl bg-black"></div>
        <p x-show="error" x-text="error" class="text-center text-base text-white"></p>
        {{-- Kartu hasil scan halaman induk (card) ikut tampil di atas kamera; pengumuman pembaca layar tetap dari x-scan.card. --}}
        <div x-show="!error && card" class="w-full max-w-sm rounded-xl border px-3.5 py-3"
             :class="card?.ok ? 'border-sukses-garis bg-sukses-muda' : 'border-gagal-garis bg-gagal-muda'">
            <div class="text-base font-semibold" :class="card?.ok ? 'text-sukses' : 'text-gagal'" x-text="card?.judul"></div>
            <div class="text-sm" :class="card?.ok ? 'text-[#1F4D33]' : 'text-[#5C2E00]'" x-text="card?.pesan"></div>
        </div>
        <p x-show="!error" class="w-full max-w-sm text-center text-sm text-white/80">
            <span x-show="terakhir">Terakhir: <span class="font-mono" x-text="terakhir"></span>.</span>
            Kamera tetap menyala. Untuk menghitung barang yang sama lagi, jauhkan kamera sekitar 1 detik lalu arahkan kembali.
        </p>
        <button type="button" @click="stop()" class="min-h-12 w-full max-w-sm rounded-xl bg-white text-lg font-semibold">Tutup kamera</button>
    </div>
</div>
