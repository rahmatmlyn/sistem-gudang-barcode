<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('#######'),
            'name' => ucfirst(fake()->words(3, true)),
            'category' => fake()->randomElement(['Sembako', 'Minuman', 'Kebersihan', 'Snack']),
            'base_unit' => 'pcs',
            'min_stock' => 10,
            'is_active' => true,
        ];
    }
}
