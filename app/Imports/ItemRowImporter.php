<?php

namespace App\Imports;

use App\Models\Item;

/** Kolom sama dengan database/dummy/items.csv; upsert per SKU. */
class ItemRowImporter extends RowImporter
{
    public function columns(): array
    {
        return ['sku', 'name', 'category', 'base_unit', 'min_stock'];
    }

    protected function rowKey(array $row): string
    {
        return $row['sku'];
    }

    protected function rules(array $row): array
    {
        return [
            'sku' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'base_unit' => ['required', 'string', 'max:30'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function persist(array $row): void
    {
        Item::query()->updateOrCreate(['sku' => $row['sku']], [
            'name' => $row['name'],
            'category' => $row['category'] ?? null,
            'base_unit' => $row['base_unit'],
            'min_stock' => (int) ($row['min_stock'] ?? 0),
        ]);
    }
}
