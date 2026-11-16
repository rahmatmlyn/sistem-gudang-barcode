<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    public function getSubheading(): ?string
    {
        return 'Data real-time dari scan';
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
