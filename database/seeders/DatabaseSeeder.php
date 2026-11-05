<?php

namespace Database\Seeders;

use App\Models\Institusi;
use App\Models\Mahasiswa;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Idempotent: aman dijalankan berulang (dipakai oleh entrypoint Docker).
 * Secret HMAC dibaca dari .env (lihat config/sevima.php), tidak pernah ditulis di sini.
 */
class DatabaseSeeder extends Seeder
{
    private const NAMA_MAHASISWA = ['Budi Santoso', 'Siti Rahmawati', 'Andi Pratama'];

    public function run(): void
    {
        $password = (string) config('sevima.demo_password');

        foreach (config('sevima.seed_institusi') as $def) {
            $institusi = Institusi::firstOrNew(['kode' => $def['kode']]);
            $institusi->nama = $def['nama'];

            if (filled($def['secret'])) {
                $institusi->webhook_secret = $def['secret'];
            } elseif (! $institusi->exists) {
                if (! app()->environment(['local', 'testing'])) {
                    throw new RuntimeException("Secret HMAC untuk {$def['kode']} belum diisi ({$def['secret_env']}).");
                }

                $this->command?->warn("{$def['secret_env']} kosong di .env; memakai secret acak. Webhook dari mock gateway akan ditolak sampai diisi lalu `php artisan db:seed` dijalankan ulang.");
                $institusi->webhook_secret = Str::random(48);
            }

            $institusi->save();

            User::updateOrCreate(
                ['email' => "admin.{$def['slug']}@example.test"],
                [
                    'name' => 'Admin Keuangan '.strtoupper($def['slug']),
                    'password' => $password,
                    'institusi_id' => $institusi->id,
                    'role' => User::ROLE_ADMIN_KEUANGAN,
                ]
            );

            foreach (self::NAMA_MAHASISWA as $i => $nama) {
                $n = $i + 1;
                $urut = str_pad((string) $n, 3, '0', STR_PAD_LEFT);

                $user = User::updateOrCreate(
                    ['email' => "mhs{$n}.{$def['slug']}@example.test"],
                    [
                        'name' => $nama,
                        'password' => $password,
                        'institusi_id' => $institusi->id,
                        'role' => User::ROLE_MAHASISWA,
                    ]
                );

                // NIM dan nomor tagihan sengaja sama di tiap institusi: unik per institusi, bukan global.
                $mahasiswa = Mahasiswa::updateOrCreate(
                    ['institusi_id' => $institusi->id, 'nim' => '2026'.$urut],
                    ['user_id' => $user->id, 'nama' => $nama, 'program_studi' => 'Teknik Informatika']
                );

                Tagihan::firstOrCreate(
                    ['institusi_id' => $institusi->id, 'nomor_tagihan' => 'UKT-2026-'.$urut],
                    [
                        'mahasiswa_id' => $mahasiswa->id,
                        'semester' => '2026/2027 Ganjil',
                        'nominal' => 2_500_000,
                        'tanggal_kedaluwarsa' => now()->addDays(30),
                        'status' => Tagihan::BELUM_BAYAR,
                    ]
                );
            }
        }
    }
}
