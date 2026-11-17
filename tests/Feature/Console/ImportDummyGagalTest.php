<?php

use App\Models\Item;
use App\Models\User;

it('import yang gagal di tengah tidak meninggalkan data setengah jadi', function () {
    $folder = 'storage/framework/testing/dummy-rusak';
    @mkdir(base_path($folder), 0777, true);
    foreach (glob(base_path('tests/Fixtures/dummy/*.csv')) as $file) {
        copy($file, base_path($folder.'/'.basename($file)));
    }
    file_put_contents(base_path("{$folder}/items.csv"), "sku,name,category,base_unit,min_stock\nSKU-00009,,Sembako,pcs,1\n");

    $this->artisan('gudang:import-dummy', ['--path' => $folder])->expectsOutputToContain('items')->assertFailed();

    expect(User::count())->toBe(0)->and(Item::count())->toBe(0);
});
