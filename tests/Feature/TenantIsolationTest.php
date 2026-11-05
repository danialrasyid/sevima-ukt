<?php

namespace Tests\Feature;

use App\Models\Tagihan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Isolasi data antar-institusi: inti dari tes ini.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_admin_hanya_melihat_tagihan_institusinya_di_halaman_daftar(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $this->tagihan($a, $this->mahasiswa($a, '1'), 'UKT-A1');
        $this->tagihan($b, $this->mahasiswa($b, '1'), 'UKT-B1');

        $this->actingAs($this->admin($a))
            ->get('/admin/tagihan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Tagihan/Index')
                ->has('tagihan.data', 1)
                ->where('tagihan.data.0.nomor_tagihan', 'UKT-A1')
                ->has('mahasiswa', 1)
            );
    }

    public function test_global_scope_membatasi_query_model(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $tA = $this->tagihan($a, $this->mahasiswa($a, '1'), 'UKT-A1');
        $tB = $this->tagihan($b, $this->mahasiswa($b, '1'), 'UKT-B1');

        $this->actingAs($this->admin($a));

        $this->assertSame(1, Tagihan::count());
        $this->assertSame(2, Tagihan::withoutGlobalScopes()->count());
        $this->assertNotNull(Tagihan::find($tA->id));
        $this->assertNull(Tagihan::find($tB->id));
    }

    public function test_pengguna_login_tidak_boleh_membuat_data_untuk_institusi_lain(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $mB = $this->mahasiswa($b, '1');

        $this->actingAs($this->admin($a));

        $this->expectException(LogicException::class);

        Tagihan::create([
            'institusi_id' => $b->id,
            'mahasiswa_id' => $mB->id,
            'nomor_tagihan' => 'UKT-X',
            'semester' => '2026/2027 Ganjil',
            'nominal' => 1_000_000,
            'tanggal_kedaluwarsa' => now()->addDay(),
            'status' => Tagihan::BELUM_BAYAR,
        ]);
    }

    public function test_admin_tidak_bisa_melihat_tagihan_institusi_lain_lewat_api(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $tB = $this->tagihan($b, $this->mahasiswa($b, '1'), 'UKT-B1');

        $this->actingAs($this->admin($a))
            ->getJson("/api/tagihan/{$tB->id}")
            ->assertNotFound();
    }

    public function test_admin_tidak_bisa_melihat_tagihan_mahasiswa_institusi_lain_lewat_api(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $mB = $this->mahasiswa($b, '1');
        $this->tagihan($b, $mB, 'UKT-B1');

        $this->actingAs($this->admin($a))
            ->getJson("/api/mahasiswa/{$mB->id}/tagihan")
            ->assertNotFound();
    }

    public function test_admin_tidak_bisa_membuat_tagihan_untuk_mahasiswa_institusi_lain(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $mB = $this->mahasiswa($b, '1');
        $admin = $this->admin($a);

        $this->actingAs($admin)
            ->post('/admin/tagihan', $this->formTagihan($mB->id))
            ->assertSessionHasErrors('mahasiswa_id');

        $this->assertSame(0, Tagihan::withoutGlobalScopes()->count());
    }

    public function test_institusi_id_dari_input_diabaikan(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $mA = $this->mahasiswa($a, '1');
        $admin = $this->admin($a);

        $this->actingAs($admin)
            ->post('/admin/tagihan', $this->formTagihan($mA->id) + ['institusi_id' => $b->id])
            ->assertSessionHasNoErrors();

        $tagihan = Tagihan::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($a->id, $tagihan->institusi_id);
        $this->assertSame(Tagihan::BELUM_BAYAR, $tagihan->status);
    }

    public function test_nomor_tagihan_boleh_sama_di_institusi_berbeda_tetapi_tidak_dalam_satu_institusi(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $mA1 = $this->mahasiswa($a, '1');
        $mA2 = $this->mahasiswa($a, '2');
        $this->tagihan($b, $this->mahasiswa($b, '1'), 'UKT-001');
        $admin = $this->admin($a);

        // Sama dengan nomor milik institusi B: boleh.
        $this->actingAs($admin)
            ->post('/admin/tagihan', $this->formTagihan($mA1->id, ['nomor_tagihan' => 'UKT-001']))
            ->assertSessionHasNoErrors();

        // Sama di institusi yang sama: ditolak.
        $this->actingAs($admin)
            ->post('/admin/tagihan', $this->formTagihan($mA2->id, ['nomor_tagihan' => 'UKT-001']))
            ->assertSessionHasErrors('nomor_tagihan');

        $this->assertSame(2, Tagihan::withoutGlobalScopes()->where('nomor_tagihan', 'UKT-001')->count());
    }

    public function test_validasi_form_tagihan(): void
    {
        $a = $this->institusi('UNIV-A');
        $mA = $this->mahasiswa($a, '1');

        $this->actingAs($this->admin($a))
            ->post('/admin/tagihan', $this->formTagihan($mA->id, [
                'nominal' => 0,
                'tanggal_kedaluwarsa' => now()->subDay()->toIso8601String(),
            ]))
            ->assertSessionHasErrors(['nominal', 'tanggal_kedaluwarsa']);
    }

    public function test_satu_mahasiswa_hanya_punya_satu_tagihan_per_semester(): void
    {
        $a = $this->institusi('UNIV-A');
        $mA = $this->mahasiswa($a, '1');
        $this->tagihan($a, $mA, 'UKT-001');

        $this->actingAs($this->admin($a))
            ->post('/admin/tagihan', $this->formTagihan($mA->id, ['nomor_tagihan' => 'UKT-002']))
            ->assertSessionHasErrors('semester');
    }

    public function test_filter_status_di_halaman_admin(): void
    {
        $a = $this->institusi('UNIV-A');
        $this->tagihan($a, $this->mahasiswa($a, '1'), 'T-BELUM');
        $this->tagihan($a, $this->mahasiswa($a, '2'), 'T-LUNAS', ['status' => Tagihan::LUNAS, 'paid_at' => now()]);
        // Belum bayar tetapi sudah lewat batas: status efektifnya kedaluwarsa.
        $this->tagihan($a, $this->mahasiswa($a, '3'), 'T-LEWAT', ['tanggal_kedaluwarsa' => now()->subHour()]);

        $admin = $this->admin($a);

        $this->actingAs($admin)->get('/admin/tagihan?status=lunas')
            ->assertInertia(fn (Assert $p) => $p->has('tagihan.data', 1)->where('tagihan.data.0.nomor_tagihan', 'T-LUNAS'));

        $this->actingAs($admin)->get('/admin/tagihan?status=belum_bayar')
            ->assertInertia(fn (Assert $p) => $p->has('tagihan.data', 1)->where('tagihan.data.0.nomor_tagihan', 'T-BELUM'));

        $this->actingAs($admin)->get('/admin/tagihan?status=kedaluwarsa')
            ->assertInertia(fn (Assert $p) => $p->has('tagihan.data', 1)->where('tagihan.data.0.nomor_tagihan', 'T-LEWAT'));
    }

    public function test_mahasiswa_tidak_bisa_melihat_tagihan_mahasiswa_lain_di_institusi_yang_sama(): void
    {
        $a = $this->institusi('UNIV-A');
        $m1 = $this->mahasiswa($a, '1');
        $m2 = $this->mahasiswa($a, '2');
        $t2 = $this->tagihan($a, $m2, 'UKT-002');

        $this->actingAs($m1->user)->getJson("/api/tagihan/{$t2->id}")->assertForbidden();
        $this->actingAs($m1->user)->getJson("/api/mahasiswa/{$m2->id}/tagihan")->assertForbidden();
    }

    public function test_mahasiswa_melihat_status_dan_riwayat_miliknya_tanpa_payload_mentah(): void
    {
        $a = $this->institusi('UNIV-A');
        $m1 = $this->mahasiswa($a, '1');
        $t1 = $this->tagihan($a, $m1, 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'))->assertOk();

        $this->actingAs($m1->user)
            ->getJson("/api/mahasiswa/{$m1->id}/tagihan")
            ->assertOk()
            ->assertJsonPath('data.bisa_isi_krs', true)
            ->assertJsonPath('data.tagihan.0.status', 'lunas')
            ->assertJsonPath('data.tagihan.0.riwayat.0.event_type', 'payment.success')
            ->assertJsonMissingPath('data.tagihan.0.riwayat.0.payload')
            ->assertJsonMissingPath('data.tagihan.0.riwayat.0.event_id');

        $this->actingAs($m1->user)->getJson("/api/tagihan/{$t1->id}")->assertOk();
    }

    public function test_mahasiswa_tidak_bisa_membuka_halaman_admin(): void
    {
        $a = $this->institusi('UNIV-A');
        $m1 = $this->mahasiswa($a, '1');

        $this->actingAs($m1->user)->get('/admin/tagihan')->assertForbidden();
        $this->actingAs($m1->user)->post('/admin/tagihan', $this->formTagihan($m1->id))->assertForbidden();
    }

    public function test_pengguna_tanpa_login_tidak_bisa_mengakses_api(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a, '1'), 'UKT-001');

        $this->getJson("/api/tagihan/{$t->id}")->assertUnauthorized();
        $this->get('/admin/tagihan')->assertRedirect('/login');
    }

    private function formTagihan(int $mahasiswaId, array $override = []): array
    {
        return array_merge([
            'mahasiswa_id' => $mahasiswaId,
            'nomor_tagihan' => 'UKT-NEW',
            'semester' => '2026/2027 Ganjil',
            'nominal' => 2_500_000,
            'tanggal_kedaluwarsa' => now()->addDays(10)->toIso8601String(),
        ], $override);
    }
}
