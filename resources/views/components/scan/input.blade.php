@props(['label' => 'Scan barcode', 'placeholder' => 'Arahkan scanner ke barcode'])
<div class="flex flex-col gap-1.5">
    <label for="scan-input" class="text-[13px] font-semibold text-[#3B424D]" {{ $attributes->only('x-text') }}>{{ $label }}</label>
    <div class="flex h-14 items-center gap-2.5 rounded-xl border-2 border-aksen bg-white px-3.5">
        <svg viewBox="0 0 24 24" class="size-6 shrink-0 text-aksen" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V5a1 1 0 011-1h2M17 4h2a1 1 0 011 1v2M20 17v2a1 1 0 01-1 1h-2M7 20H5a1 1 0 01-1-1v-2M7 8v8M10 8v8M13 8v8M17 8v8"/></svg>
        <input id="scan-input" type="text" x-data="scanInput()" x-model="value" @keydown.enter.prevent="submit()"
               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" enterkeyhint="go" autofocus
               placeholder="{{ $placeholder }}"
               class="min-h-11 grow border-none bg-transparent text-lg outline-none">
    </div>
</div>
