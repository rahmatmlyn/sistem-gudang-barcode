<?php

namespace App\Rules;

use App\Models\Location;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Parent lokasi: ada, di gudang yang sama, dan bukan lokasi itu sendiri atau turunannya (hierarki tanpa siklus). */
class ParentLokasiValid implements ValidationRule
{
    public function __construct(private ?Location $location, private int|string|null $warehouseId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parent = Location::query()->find($value);

        if ($parent === null) {
            $fail('Lokasi induk tidak ditemukan.');
        } elseif ((int) $parent->warehouse_id !== (int) $this->warehouseId) {
            $fail('Lokasi induk harus berada di gudang yang sama.');
        } elseif ($this->location?->exists && $parent->is($this->location)) {
            $fail('Lokasi tidak boleh menjadi induk dirinya sendiri.');
        } elseif ($this->location?->exists && in_array($parent->id, $this->location->descendantIds(), true)) {
            $fail("Lokasi {$parent->code} adalah turunan {$this->location->code}, tidak bisa menjadi induknya.");
        }
    }
}
