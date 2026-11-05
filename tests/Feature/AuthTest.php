<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_admin_diarahkan_ke_halaman_admin_setelah_login(): void
    {
        $a = $this->institusi('UNIV-A');
        $admin = $this->admin($a);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/tagihan');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_mahasiswa_diarahkan_ke_halaman_mahasiswa_setelah_login(): void
    {
        $a = $this->institusi('UNIV-A');
        $mahasiswa = $this->mahasiswa($a, '1');

        $this->post('/login', ['email' => $mahasiswa->user->email, 'password' => 'password'])
            ->assertRedirect('/mahasiswa/tagihan');
    }

    public function test_password_salah_ditolak(): void
    {
        $a = $this->institusi('UNIV-A');
        $admin = $this->admin($a);

        $this->post('/login', ['email' => $admin->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
