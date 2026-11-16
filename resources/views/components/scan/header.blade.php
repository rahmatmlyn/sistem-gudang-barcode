@props(['title', 'back' => '/scan'])
<header class="sticky top-0 z-10 flex flex-col gap-2.5 border-b border-garis bg-white px-4 py-3">
    <div class="flex items-center gap-3">
        <a href="{{ $back }}" aria-label="Kembali"
           class="flex size-11 shrink-0 items-center justify-center rounded-[10px] border border-garis bg-white">
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div class="flex min-w-0 grow flex-col gap-0.5">
            <h1 class="text-[17px] font-semibold">{{ $title }}</h1>
            <div class="truncate text-[13px] text-redup">{{ $sub ?? '' }}</div>
        </div>
        {{ $slot }}
        <span x-data class="rounded-full px-2.5 py-1.5 text-xs font-semibold"
              :class="$store.scan.online ? 'bg-sukses-muda text-sukses' : 'bg-gagal-muda text-gagal'"
              x-text="$store.scan.online ? 'Online' : 'Offline'">Online</span>
    </div>
    {{ $progress ?? '' }}
</header>
