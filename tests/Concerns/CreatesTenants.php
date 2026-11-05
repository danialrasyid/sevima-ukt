<?php

namespace Tests\Concerns;

use App\Models\Institusi;
use App\Models\Mahasiswa;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Helper pembuat data multi-institusi.
 * Buat SEMUA data lebih dulu, baru actingAs(): saat sudah login, trait
 * BelongsToInstitusi melarang membuat data untuk institusi lain.
 */
trait CreatesTenants
{
    protected function institusi(string $kode, ?string $secret = null): Institusi
    {
        return Institusi::create([
            'kode' => $kode,
            'nama' => "Kampus {$kode}",
            'webhook_secret' => $secret ?? "secret-{$kode}",
        ]);
    }

    protected function admin(Institusi $institusi): User
    {
        return User::create([
            'name' => "Admin {$institusi->kode}",
            'email' => strtolower($institusi->kode).'-admin@test.local',
            'password' => 'password',
            'institusi_id' => $institusi->id,
            'role' => User::ROLE_ADMIN_KEUANGAN,
        ]);
    }

    protected function mahasiswa(Institusi $institusi, string $nim = '2026001'): Mahasiswa
    {
        $user = User::create([
            'name' => "Mhs {$nim}",
            'email' => strtolower($institusi->kode)."-{$nim}@test.local",
            'password' => 'password',
            'institusi_id' => $institusi->id,
            'role' => User::ROLE_MAHASISWA,
        ]);

        return Mahasiswa::create([
            'institusi_id' => $institusi->id,
            'user_id' => $user->id,
            'nim' => $nim,
            'nama' => "Mhs {$nim}",
        ]);
    }

    protected function tagihan(Institusi $institusi, Mahasiswa $mahasiswa, string $nomor = 'UKT-001', array $override = []): Tagihan
    {
        return Tagihan::create(array_merge([
            'institusi_id' => $institusi->id,
            'mahasiswa_id' => $mahasiswa->id,
            'nomor_tagihan' => $nomor,
            'semester' => '2026/2027 Ganjil',
            'nominal' => 5_000_000,
            'tanggal_kedaluwarsa' => now()->addDays(14),
            'status' => Tagihan::BELUM_BAYAR,
        ], $override));
    }

    protected function payload(Institusi $institusi, string $nomor, string $event, array $override = []): array
    {
        return array_merge([
            'event_id' => (string) Str::uuid(),
            'event' => $event,
            'institusi' => $institusi->kode,
            'nomor_tagihan' => $nomor,
            'amount' => 5_000_000,
            'paid_at' => now()->toIso8601String(),
        ], $override);
    }

    /**
     * Kirim webhook dengan body mentah + signature HMAC. Secret/signature bisa
     * ditimpa untuk menguji kasus salah secret atau signature rusak.
     */
    protected function kirimWebhook(Institusi $institusi, array $payload, ?string $secret = null, ?string $signature = null): TestResponse
    {
        $raw = json_encode($payload);
        $signature ??= hash_hmac('sha256', $raw, $secret ?? $institusi->webhook_secret);

        return $this->kirimWebhookMentah($raw, $signature);
    }

    protected function kirimWebhookMentah(string $raw, ?string $signature): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($signature !== null) {
            $server['HTTP_X_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/api/webhooks/payment', [], [], [], $server, $raw);
    }
}
