<x-filament-panels::page>
    {{ $this->form }}

    @if ($laporan = $this->report())
        <div class="grid grid-cols-2 gap-4">
            <x-filament::section compact>
                <div class="text-sm text-gray-500">Saldo awal</div>
                <div class="text-2xl font-semibold">{{ number_format($laporan->saldoAwal(), 0, ',', '.') }}</div>
            </x-filament::section>
            <x-filament::section compact>
                <div class="text-sm text-gray-500">Saldo akhir</div>
                <div class="text-2xl font-semibold">{{ number_format($laporan->saldoAkhir(), 0, ',', '.') }}</div>
            </x-filament::section>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
