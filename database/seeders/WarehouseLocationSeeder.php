<?php

namespace Database\Seeders;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseLocationSeeder extends Seeder
{
    public function run(): void
    {
        $gudang = Warehouse::updateOrCreate(['code' => 'GU'], ['name' => 'Gudang Utama']);

        $this->lokasi($gudang, 'RCV-01', LocationType::AreaTerima, 'Area Terima');
        $this->lokasi($gudang, 'SHP-01', LocationType::AreaKirim, 'Area Kirim');

        foreach (['A', 'B'] as $kodeZona) {
            $zona = $this->lokasi($gudang, $kodeZona, LocationType::Zona, "Zona {$kodeZona}");

            foreach (['01', '02'] as $nomorRak) {
                $rak = $this->lokasi($gudang, "{$kodeZona}-{$nomorRak}", LocationType::Rak, parent: $zona);

                foreach (['01', '02', '03'] as $nomorBin) {
                    $this->lokasi($gudang, "{$rak->code}-{$nomorBin}", LocationType::Bin, parent: $rak);
                }
            }
        }
    }

    private function lokasi(Warehouse $gudang, string $code, LocationType $type, ?string $name = null, ?Location $parent = null): Location
    {
        return Location::updateOrCreate(
            ['warehouse_id' => $gudang->id, 'code' => $code],
            ['type' => $type, 'name' => $name, 'parent_id' => $parent?->id, 'barcode' => "LOC-{$code}"],
        );
    }
}
