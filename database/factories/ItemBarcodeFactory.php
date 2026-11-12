<?php

namespace Database\Factories;

use App\Enums\BarcodeType;
use App\Models\Item;
use App\Models\ItemBarcode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemBarcode> */
class ItemBarcodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'barcode' => 'BC-'.fake()->unique()->numerify('##########'),
            'type' => BarcodeType::Code128,
            'unit' => 'pcs',
            'factor' => 1,
        ];
    }

    public function dus(int $factor): static
    {
        return $this->state(['unit' => 'dus', 'factor' => $factor]);
    }
}
