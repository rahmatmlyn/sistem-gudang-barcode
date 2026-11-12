<?php

use App\Enums\UserRole;
use App\Models\User;

function pengguna(UserRole $role): User
{
    return User::factory()->create(['username' => 'u_'.$role->value, 'role' => $role]);
}

it('login memakai username', function () {
    $staf = pengguna(UserRole::Staf);

    $this->post('/login', ['username' => 'u_staf', 'password' => 'password']);

    $this->assertAuthenticatedAs($staf);
});

it('menolak login dengan password salah', function () {
    pengguna(UserRole::Staf);

    $this->post('/login', ['username' => 'u_staf', 'password' => 'salah'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('mengarahkan staf ke /scan setelah login', function () {
    pengguna(UserRole::Staf);

    $this->post('/login', ['username' => 'u_staf', 'password' => 'password'])
        ->assertRedirect('/scan');
});

it('mengarahkan admin dan supervisor ke /admin setelah login', function (UserRole $role) {
    pengguna($role);

    $this->post('/login', ['username' => 'u_'.$role->value, 'password' => 'password'])
        ->assertRedirect('/admin');
})->with([UserRole::Admin, UserRole::Supervisor]);

it('menolak staf membuka /admin', function () {
    $this->actingAs(pengguna(UserRole::Staf))
        ->get('/admin')
        ->assertForbidden();
});

it('mengizinkan admin dan supervisor membuka /admin', function (UserRole $role) {
    $this->actingAs(pengguna($role))
        ->get('/admin')
        ->assertOk();
})->with([UserRole::Admin, UserRole::Supervisor]);

it('mengizinkan semua peran membuka /scan', function (UserRole $role) {
    $this->actingAs(pengguna($role))
        ->get('/scan')
        ->assertOk();
})->with(UserRole::cases());

it('menolak tamu membuka /scan dan /admin', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/scan', '/admin']);

it('logout mengembalikan ke halaman login', function () {
    $this->actingAs(pengguna(UserRole::Staf))
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

it('menampilkan halaman login berbahasa Indonesia', function () {
    $this->get('/login')->assertOk()->assertSee('Masuk dengan username dan password.');
});

it('mengarahkan pengguna yang sudah login dari /login ke halaman perannya', function (UserRole $role, string $tujuan) {
    $this->actingAs(pengguna($role))
        ->get('/login')
        ->assertRedirect($tujuan);
})->with([
    [UserRole::Staf, '/scan'],
    [UserRole::Admin, '/admin'],
]);
