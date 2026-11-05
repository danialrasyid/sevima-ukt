<?php

namespace Tests\Feature;

use App\Models\Tagihan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ExpireTagihanTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_status_efektif_mencerminkan_kedaluwarsa_sebelum_command_berjalan(): void
    {
        $a = $this->institusi('UNIV-A');
        $lewat = $this->tagihan($a, $this->mahasiswa($a, '1'), 'T-1', ['tanggal_kedaluwarsa' => now()->subHour()]);
        $baru = $this->tagihan($a, $this->mahasiswa($a, '2'), 'T-2');

        $this->assertSame(Tagihan::BELUM_BAYAR, $lewat->status);
        $this->assertSame(Tagihan::KEDALUWARSA, $lewat->status_efektif);
        $this->assertSame(Tagihan::BELUM_BAYAR, $baru->status_efektif);
    }

    public function test_command_hanya_mengubah_belum_bayar_yang_sudah_lewat_batas(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');

        $lewatA = $this->tagihan($a, $this->mahasiswa($a, '1'), 'T-1', ['tanggal_kedaluwarsa' => now()->subHour()]);
        $lewatB = $this->tagihan($b, $this->mahasiswa($b, '1'), 'T-1', ['tanggal_kedaluwarsa' => now()->subHour()]);
        $lunas = $this->tagihan($a, $this->mahasiswa($a, '2'), 'T-2', [
            'tanggal_kedaluwarsa' => now()->subHour(),
            'status' => Tagihan::LUNAS,
            'paid_at' => now()->subDay(),
        ]);
        $baru = $this->tagihan($a, $this->mahasiswa($a, '3'), 'T-3');

        $this->artisan('tagihan:expire')->assertSuccessful();

        $this->assertSame(Tagihan::KEDALUWARSA, $lewatA->fresh()->status);
        $this->assertSame(Tagihan::KEDALUWARSA, $lewatB->fresh()->status);
        $this->assertSame(Tagihan::LUNAS, $lunas->fresh()->status);
        $this->assertSame(Tagihan::BELUM_BAYAR, $baru->fresh()->status);

        $this->assertDatabaseHas('transaksi', ['tagihan_id' => $lewatA->id, 'event_type' => 'system.expired']);
        $this->assertDatabaseMissing('transaksi', ['tagihan_id' => $lunas->id]);
    }

    public function test_command_aman_dijalankan_berulang(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a, '1'), 'T-1', ['tanggal_kedaluwarsa' => now()->subHour()]);

        $this->artisan('tagihan:expire')->assertSuccessful();
        $this->artisan('tagihan:expire')->assertSuccessful();

        $this->assertSame(1, \App\Models\Transaksi::withoutGlobalScopes()->where('tagihan_id', $t->id)->count());
    }
}
