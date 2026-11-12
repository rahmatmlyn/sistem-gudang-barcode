<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    /** Password default hanya untuk pengembangan; tidak boleh dipakai di production. */
    public const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('UserSeeder membuat user dengan password default dan tidak boleh dijalankan di production.');
        }

        $users = [
            ['username' => 'admin', 'name' => 'Admin Gudang', 'role' => UserRole::Admin],
            ['username' => 'supervisor', 'name' => 'Supervisor Gudang', 'role' => UserRole::Supervisor],
            ['username' => 'staf01', 'name' => 'Staf Gudang 01', 'role' => UserRole::Staf],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                ['name' => $user['name'], 'role' => $user['role'], 'password' => self::DEFAULT_PASSWORD],
            );
        }
    }
}
