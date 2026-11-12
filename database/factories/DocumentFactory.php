<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'TEST-'.fake()->unique()->numerify('######'),
            'type' => DocumentType::GRN,
            'status' => DocumentStatus::Draft,
            'warehouse_id' => Warehouse::factory(),
            'created_by' => User::factory(),
        ];
    }

    public function ofType(DocumentType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
