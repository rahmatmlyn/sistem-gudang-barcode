<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Reports\DashboardSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $angka = DashboardSummary::get();
        $fmt = fn (int $value) => number_format($value, 0, ',', '.');

        return [
            Stat::make('SKU aktif', $fmt($angka['sku_aktif'])),
            Stat::make('Di bawah stok minimum', $fmt($angka['di_bawah_minimum']))->color($angka['di_bawah_minimum'] ? 'warning' : 'gray'),
            Stat::make('Dokumen berjalan', $fmt($angka['dokumen_berjalan'])),
            Stat::make('Selisih opname menunggu approval', $fmt($angka['menunggu_approval']))
                ->color($angka['menunggu_approval'] ? 'danger' : 'gray')
                ->url(StockOpnameResource::getUrl('index')),
        ];
    }
}
