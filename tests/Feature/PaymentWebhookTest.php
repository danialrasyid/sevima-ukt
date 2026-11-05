<?php

namespace Tests\Feature;

use App\Models\Tagihan;
use App\Models\Transaksi;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_signature_valid_melunasi_tagihan_dan_mencatat_transaksi_dan_log(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'))
            ->assertOk()
            ->assertJsonPath('result', 'processed')
            ->assertJsonPath('tagihan.status', 'lunas');

        $t->refresh();
        $this->assertSame(Tagihan::LUNAS, $t->status);
        $this->assertNotNull($t->paid_at);

        $this->assertDatabaseHas('transaksi', [
            'tagihan_id' => $t->id,
            'event_type' => 'payment.success',
            'hasil' => 'diterapkan',
            'status_sebelum' => 'belum_bayar',
            'status_sesudah' => 'lunas',
        ]);
        $this->assertDatabaseHas('webhook_logs', [
            'institusi_id' => $a->id,
            'outcome' => 'diproses',
            'signature_valid' => true,
            'http_status' => 200,
        ]);
    }

    public function test_signature_boleh_berawalan_sha256(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $raw = json_encode($this->payload($a, 'UKT-001', 'payment.success'));
        $this->kirimWebhookMentah($raw, 'sha256='.hash_hmac('sha256', $raw, $a->webhook_secret))->assertOk();

        $this->assertSame(Tagihan::LUNAS, $t->fresh()->status);
    }

    public function test_signature_salah_ditolak_dan_tetap_dicatat(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'), signature: str_repeat('a', 64))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'signature_tidak_valid');

        $this->assertSame(Tagihan::BELUM_BAYAR, $t->fresh()->status);
        $this->assertSame(0, Transaksi::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('webhook_logs', [
            'institusi_id' => $a->id,
            'outcome' => 'signature_ditolak',
            'signature_valid' => false,
            'http_status' => 401,
        ]);
    }

    public function test_tanpa_header_signature_ditolak(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $raw = json_encode($this->payload($a, 'UKT-001', 'payment.success'));
        $this->kirimWebhookMentah($raw, null)->assertUnauthorized();

        $this->assertSame(Tagihan::BELUM_BAYAR, $t->fresh()->status);
        $this->assertSame(1, WebhookLog::count());
    }

    public function test_secret_institusi_lain_tidak_bisa_memvalidasi_payload(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $tA = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        // Payload untuk institusi A, tetapi ditandatangani dengan secret institusi B.
        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'), secret: $b->webhook_secret)
            ->assertUnauthorized();

        $this->assertSame(Tagihan::BELUM_BAYAR, $tA->fresh()->status);
    }

    public function test_institusi_tidak_dikenal_ditolak_dan_dicatat(): void
    {
        $a = $this->institusi('UNIV-A');
        $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $payload = $this->payload($a, 'UKT-001', 'payment.success', ['institusi' => 'TIDAK-ADA']);

        $this->kirimWebhook($a, $payload)->assertUnauthorized();

        $this->assertDatabaseHas('webhook_logs', [
            'institusi_id' => null,
            'kode_institusi' => 'TIDAK-ADA',
            'outcome' => 'signature_ditolak',
        ]);
    }

    public function test_nomor_tagihan_sama_di_dua_institusi_hanya_mengubah_institusi_pengirim(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $tA = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');
        $tB = $this->tagihan($b, $this->mahasiswa($b), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'))->assertOk();

        $this->assertSame(Tagihan::LUNAS, $tA->fresh()->status);
        $this->assertSame(Tagihan::BELUM_BAYAR, $tB->fresh()->status);
    }

    public function test_tagihan_tidak_ditemukan_menghasilkan_404_dan_dicatat(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $this->tagihan($b, $this->mahasiswa($b), 'UKT-HANYA-B');

        // Nomor ada di institusi B, tetapi pengirimnya institusi A.
        $this->kirimWebhook($a, $this->payload($a, 'UKT-HANYA-B', 'payment.success'))
            ->assertNotFound()
            ->assertJsonPath('code', 'tagihan_tidak_ditemukan');

        $this->assertDatabaseHas('webhook_logs', ['institusi_id' => $a->id, 'outcome' => 'tagihan_tidak_ditemukan']);
    }

    public function test_event_duplikat_bersifat_idempoten(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');
        $payload = $this->payload($a, 'UKT-001', 'payment.success', ['event_id' => 'evt-sama']);

        $this->kirimWebhook($a, $payload)->assertOk()->assertJsonPath('result', 'processed');
        $this->kirimWebhook($a, $payload)->assertOk()->assertJsonPath('result', 'duplicate');

        $this->assertSame(1, Transaksi::withoutGlobalScopes()->where('tagihan_id', $t->id)->count());
        $this->assertSame(2, WebhookLog::count());
        $this->assertDatabaseHas('webhook_logs', ['outcome' => 'duplikat']);
        $this->assertSame(Tagihan::LUNAS, $t->fresh()->status);
    }

    public function test_event_id_boleh_sama_di_institusi_berbeda(): void
    {
        $a = $this->institusi('UNIV-A');
        $b = $this->institusi('UNIV-B');
        $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');
        $this->tagihan($b, $this->mahasiswa($b), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success', ['event_id' => 'evt-1']))
            ->assertJsonPath('result', 'processed');
        $this->kirimWebhook($b, $this->payload($b, 'UKT-001', 'payment.success', ['event_id' => 'evt-1']))
            ->assertJsonPath('result', 'processed');

        $this->assertSame(2, Transaksi::withoutGlobalScopes()->count());
    }

    public function test_event_expired_mengubah_belum_bayar_menjadi_kedaluwarsa(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.expired', ['amount' => null]))
            ->assertOk()
            ->assertJsonPath('tagihan.status', 'kedaluwarsa');

        $this->assertSame(Tagihan::KEDALUWARSA, $t->fresh()->status);
    }

    public function test_event_expired_setelah_lunas_diabaikan(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'))->assertOk();
        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.expired'))
            ->assertOk()
            ->assertJsonPath('result', 'ignored');

        $this->assertSame(Tagihan::LUNAS, $t->fresh()->status);
        $this->assertDatabaseHas('transaksi', ['tagihan_id' => $t->id, 'event_type' => 'payment.expired', 'hasil' => 'diabaikan']);
    }

    public function test_pembayaran_setelah_kedaluwarsa_tetap_melunasi_karena_uang_diterima(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.expired'))->assertOk();
        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'))
            ->assertOk()
            ->assertJsonPath('tagihan.status', 'lunas');

        $this->assertSame(Tagihan::LUNAS, $t->fresh()->status);
        $this->assertDatabaseHas('transaksi', [
            'tagihan_id' => $t->id,
            'event_type' => 'payment.success',
            'status_sebelum' => 'kedaluwarsa',
            'status_sesudah' => 'lunas',
        ]);
    }

    public function test_nominal_tidak_sesuai_ditandai_dan_tidak_melunasi(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success', ['amount' => 1_000]))
            ->assertOk()
            ->assertJsonPath('result', 'flagged');

        $t->refresh();
        $this->assertSame(Tagihan::BELUM_BAYAR, $t->status);
        $this->assertTrue($t->perlu_review);
        $this->assertDatabaseHas('transaksi', ['tagihan_id' => $t->id, 'hasil' => 'ditandai']);
    }

    public function test_pembayaran_ganda_dengan_event_berbeda_ditandai_untuk_review(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'))->assertOk();
        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.success'))
            ->assertOk()
            ->assertJsonPath('result', 'ignored');

        $t->refresh();
        $this->assertSame(Tagihan::LUNAS, $t->status);
        $this->assertTrue($t->perlu_review);
        $this->assertSame(2, Transaksi::withoutGlobalScopes()->where('tagihan_id', $t->id)->count());
    }

    public function test_json_rusak_ditolak_dan_tetap_dicatat(): void
    {
        $this->institusi('UNIV-A');

        $this->kirimWebhookMentah('{bukan json', 'abc')->assertStatus(400)->assertJsonPath('code', 'payload_tidak_valid');

        $this->assertDatabaseHas('webhook_logs', ['outcome' => 'ditolak', 'http_status' => 400]);
        $this->assertSame('{bukan json', WebhookLog::firstOrFail()->raw_body);
    }

    public function test_event_tidak_dikenal_ditolak_setelah_signature_valid(): void
    {
        $a = $this->institusi('UNIV-A');
        $t = $this->tagihan($a, $this->mahasiswa($a), 'UKT-001');

        $this->kirimWebhook($a, $this->payload($a, 'UKT-001', 'payment.refunded'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'payload_tidak_valid');

        $this->assertSame(Tagihan::BELUM_BAYAR, $t->fresh()->status);
        $this->assertDatabaseHas('webhook_logs', ['institusi_id' => $a->id, 'outcome' => 'payload_ditolak', 'signature_valid' => true]);
    }

    public function test_body_dengan_byte_tidak_valid_tidak_membuat_server_error(): void
    {
        $this->institusi('UNIV-A');

        $this->kirimWebhookMentah("\xFF\xFE\x00bukan-utf8", 'abc')->assertStatus(400);

        $this->assertSame(1, WebhookLog::count());
    }
}
