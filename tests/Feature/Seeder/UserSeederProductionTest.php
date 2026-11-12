<?php

use App\Models\User;
use Database\Seeders\UserSeeder;

it('menolak membuat user dengan password default di production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(UserSeeder::class)->run())->toThrow(RuntimeException::class, 'tidak boleh dijalankan di production');
    expect(User::count())->toBe(0);
});
