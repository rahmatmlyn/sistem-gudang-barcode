<?php

namespace App\Rules;

use App\Support\BarcodeRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/** Barcode belum dipakai barang maupun lokasi lain ($ignore = record yang sedang diedit). */
class BarcodeUnik implements ValidationRule
{
    public function __construct(private ?Model $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && BarcodeRegistry::isTaken($value, $this->ignore)) {
            $fail("Barcode {$value} sudah dipakai.");
        }
    }
}
