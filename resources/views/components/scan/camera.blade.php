<div x-data="cameraScanner()">
    <button type="button" @click="start()"
            class="min-h-11 w-full rounded-xl border border-garis bg-white px-4 text-base font-semibold text-aksen">
        Scan pakai kamera
    </button>
    <div x-cloak x-show="open" class="fixed inset-0 z-40 flex flex-col items-center justify-center gap-4 bg-black/85 p-4">
        <div id="kamera-scan" class="w-full max-w-sm overflow-hidden rounded-xl bg-black"></div>
        <p x-show="error" x-text="error" class="text-center text-base text-white"></p>
        <button type="button" @click="stop()" class="min-h-12 w-full max-w-sm rounded-xl bg-white text-lg font-semibold">Tutup kamera</button>
    </div>
</div>
