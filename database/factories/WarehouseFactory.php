<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'G'.fake()->unique()->numberBetween(1, 99999),
            'name' => 'Gudang '.fake()->city(),
            'address' => fake()->address(),
        ];
    }
}
