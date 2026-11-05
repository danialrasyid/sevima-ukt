<?php

/*
 * Konfigurasi khusus aplikasi UKT.
 *
 * `kode` institusi di bawah HARUS disamakan dengan kode institusi pada seed
 * data mock payment gateway dari panitia. Secret HMAC TIDAK ditulis di sini:
 * dibaca dari .env (HMAC_SECRET_<KODE>, karakter non-alfanumerik jadi "_").
 */

$institusi = [
    ['kode' => 'UNIV-A', 'nama' => 'Universitas Contoh A', 'slug' => 'a'],
    ['kode' => 'UNIV-B', 'nama' => 'Institut Contoh B', 'slug' => 'b'],
    ['kode' => 'UNIV-C', 'nama' => 'Politeknik Contoh C', 'slug' => 'c'],
];

return [
    // Password akun demo (hanya untuk seeder lokal).
    'demo_password' => env('DEMO_PASSWORD', 'password'),

    'seed_institusi' => array_map(function (array $i): array {
        $i['secret_env'] = 'HMAC_SECRET_'.strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $i['kode']), '_'));
        $i['secret'] = env($i['secret_env']);

        return $i;
    }, $institusi),
];
