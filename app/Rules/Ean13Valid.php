<?php

namespace App\Rules;

use App\Enums\BarcodeType;
use App\Support\Ean13;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Barcode bertipe EAN-13 harus 13 digit dengan check digit benar; tipe lain tidak diperiksa. */
class Ean13Valid implements ValidationRule
{
    public function __construct(private BarcodeType|string|null $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = $this->type instanceof BarcodeType ? $this->type : BarcodeType::tryFrom((string) $this->type);

        if ($type === BarcodeType::Ean13 && ! Ean13::isValid((string) $value)) {
            $fail('Barcode EAN-13 tidak valid: harus 13 digit dengan check digit yang benar.');
        }
    }
}
