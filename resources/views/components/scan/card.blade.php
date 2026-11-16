<div x-cloak x-show="card" role="status" aria-live="assertive"
     class="flex items-start gap-3 rounded-xl border px-3.5 py-3"
     :class="card?.ok ? 'border-sukses-garis bg-sukses-muda' : 'border-gagal-garis bg-gagal-muda'">
    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
          :class="card?.ok ? 'bg-sukses' : 'bg-gagal'" x-text="card?.ok ? '✓' : '!'"></span>
    <div class="flex flex-col gap-0.5">
        <div class="text-base font-semibold" :class="card?.ok ? 'text-sukses' : 'text-gagal'" x-text="card?.judul"></div>
        <div class="text-sm" :class="card?.ok ? 'text-[#1F4D33]' : 'text-[#5C2E00]'" x-text="card?.pesan"></div>
    </div>
</div>
