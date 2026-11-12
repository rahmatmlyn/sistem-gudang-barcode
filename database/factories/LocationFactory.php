<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        $code = 'L-'.fake()->unique()->numberBetween(1, 999999);

        return [
            'warehouse_id' => Warehouse::factory(),
            'code' => $code,
            'type' => LocationType::Bin,
            'barcode' => fn (array $attributes) => 'LOC-'.$attributes['code'].'-'.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ];
    }

    public function ofType(LocationType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
