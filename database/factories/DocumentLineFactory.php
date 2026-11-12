<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentLine> */
class DocumentLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'item_id' => Item::factory(),
            'qty_planned' => 10,
            'qty_scanned' => 0,
            'qty_packed' => 0,
        ];
    }
}
