<?php

namespace App\Support;

use InvalidArgumentException;

final class Ean13
{
    /** Lengkapi 12 digit menjadi EAN-13 dengan check digit. */
    public static function complete(string $twelveDigits): string
    {
        if (! preg_match('/^\d{12}$/', $twelveDigits)) {
            throw new InvalidArgumentException('EAN-13 membutuhkan tepat 12 digit sebelum check digit.');
        }

        return $twelveDigits.self::checkDigit($twelveDigits);
    }

    public static function isValid(string $barcode): bool
    {
        return preg_match('/^\d{13}$/', $barcode) === 1
            && self::checkDigit(substr($barcode, 0, 12)) === (int) $barcode[12];
    }

    private static function checkDigit(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split($twelveDigits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }
}
