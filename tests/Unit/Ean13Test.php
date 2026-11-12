<?php

use App\Support\Ean13;

it('menghitung check digit EAN-13', function (string $dua_belas_digit, string $lengkap) {
    expect(Ean13::complete($dua_belas_digit))->toBe($lengkap);
})->with([
    ['899025201612', '8990252016121'], // dari database/dummy/item_barcodes.csv
    ['400638133393', '4006381333931'],
    ['000000000000', '0000000000000'],
]);

it('memvalidasi barcode EAN-13', function (string $barcode, bool $valid) {
    expect(Ean13::isValid($barcode))->toBe($valid);
})->with([
    ['8990252016121', true],
    ['8990252016122', false],
    ['899025201612', false],
    ['89902520161AB', false],
]);

it('menolak input yang bukan 12 digit', function () {
    Ean13::complete('12345');
})->throws(InvalidArgumentException::class);
