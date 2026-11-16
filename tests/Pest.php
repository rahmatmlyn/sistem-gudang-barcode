<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Jalankan statement yang diharapkan ditolak DB di dalam savepoint, agar transaksi
 * RefreshDatabase di PostgreSQL tidak ikut batal (SQLSTATE 25P02).
 */
function dalamSavepoint(Closure $fn): Closure
{
    return fn () => DB::transaction($fn);
}

/** Buat pengguna dengan peran tertentu dan login sebagai dia. */
function sebagai(UserRole $role): User
{
    $user = User::factory()->create(['role' => $role]);
    test()->actingAs($user);

    return $user;
}

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');
