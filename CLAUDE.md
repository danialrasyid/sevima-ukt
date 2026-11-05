# Project: Tagihan UKT: Integrasi Pembayaran via Webhook

## Deskripsi singkat

Aplikasi web fullstack multi-kampus (satu database) untuk mengelola tagihan UKT per mahasiswa per semester dan menerima notifikasi pembayaran dari payment gateway (mock) lewat webhook ber-HMAC. Status tagihan menentukan apakah mahasiswa boleh mengisi KRS, jadi harus akurat. Dibuat untuk tes teknis SEVIMA (Project M2).

## Stack dan cara menjalankan

- Laravel (PHP 8.3) + Inertia + Vue 3 + Tailwind (Vite). **Livewire dilarang.**
- PostgreSQL 16 (wajib). Test juga berjalan di PostgreSQL (database `sevima_ukt_test`).
- Jalankan: `cp .env.example .env` → isi `DB_PASSWORD`, `APP_KEY`, `HMAC_SECRET_*` → `docker compose up --build`.
- Test: `docker compose exec app php artisan test` (atau `php artisan test` jika lokal).
- Detail lengkap ada di README.md.

## Arsitektur singkat

- `routes/web.php`: halaman Inertia (admin keuangan, mahasiswa) + endpoint JSON `/api/tagihan/{id}` dan `/api/mahasiswa/{id}/tagihan` (sesi login).
- `routes/api.php`: **hanya** `POST /api/webhooks/payment` (tanpa sesi/CSRF, diautentikasi HMAC).
- `app/Services/PaymentWebhookProcessor.php`: seluruh logika webhook. Controller tipis.
- `app/Support/PaymentPayload.php`: adapter format payload gateway.
- `app/Support/TagihanPresenter.php`: satu-satunya penentu field yang boleh keluar ke UI/API.
- `app/Models/Concerns/BelongsToInstitusi.php` + `Scopes/InstitusiScope.php`: isolasi data antar-institusi.
- `app/Console/Commands/ExpireTagihan.php`: kedaluwarsa otomatis (dijadwalkan tiap menit).

## Aturan bisnis penting (termasuk multi-institusi)

- Setiap data milik satu institusi (`institusi_id`). Pengguna **hanya** boleh mengakses data institusinya sendiri.
- `institusi_id` **selalu** diambil dari pengguna login (atau dari institusi hasil verifikasi webhook), **tidak pernah** dari input request.
- Nomor tagihan unik **per institusi** (boleh sama di institusi lain). NIM juga unik per institusi.
- Satu tagihan per mahasiswa per semester.
- Webhook: signature HMAC-SHA256 di header `X-Signature`, dihitung dari **body mentah**, diverifikasi dengan secret **institusi yang disebut di payload**, dibandingkan dengan `hash_equals`.
- Semua notifikasi webhook (termasuk yang ditolak) dicatat di `webhook_logs`.
- Idempotensi: unique (`institusi_id`, `event_id`) pada `transaksi`; baris tagihan dikunci (`lockForUpdate`) saat memproses.
- Aturan status: lihat tabel di `docs/WEBHOOK.md` dan `DECISIONS.md`. Status harus mencerminkan kondisi pembayaran sebenarnya (pembayaran setelah kedaluwarsa tetap melunasi).
- Secret HMAC disimpan terenkripsi (cast `encrypted`), dibaca dari `.env` oleh seeder, tidak boleh ada di repositori atau props frontend.

## Konvensi kode

- Bahasa domain/UI/pesan: Indonesia. Nama tabel: `institusi`, `mahasiswa`, `tagihan`, `transaksi`, `webhook_logs`.
- Semua model multi-institusi memakai trait `BelongsToInstitusi`. Model baru yang punya `institusi_id` wajib memakainya.
- Validasi lewat FormRequest. Query yang melibatkan institusi memakai `Rule::exists/unique(...)->where('institusi_id', ...)`.
- Field yang keluar ke frontend lewat `TagihanPresenter`, bukan `->toArray()` model.
- Dialog/modal Vue dipisah ke file sendiri di subfolder `components/` di samping halamannya.
- Commit kecil dengan pesan bermakna (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).
- Setiap perubahan logika bisnis inti wajib disertai test, termasuk test isolasi institusi.

## Hal yang tidak boleh dilakukan AI

- Jangan pernah mengambil `institusi_id` dari request/payload untuk menentukan kepemilikan data, dan jangan mass-assign `$request->all()`.
- Jangan menulis query model multi-institusi tanpa scope. Di konteks tanpa pengguna (webhook, console) wajib `withoutGlobalScopes()` **dan** `where('institusi_id', ...)` eksplisit.
- Jangan membandingkan signature dengan `==`/`===`; jangan menghitung HMAC dari JSON hasil decode; jangan mem-verifikasi sebelum institusi ditentukan.
- Jangan menulis, mencetak, atau meng-commit secret HMAC, `APP_KEY`, atau kredensial (termasuk di test, seeder, README, log).
- Jangan menghapus/menonaktifkan global scope, unique constraint, atau foreign key komposit untuk "memperbaiki" error.
- Jangan membuang log audit webhook atau menolak pencatatan notifikasi yang ditolak.
- Jangan memakai Livewire, dan jangan mengganti PostgreSQL dengan database lain (termasuk di test).
- Jangan mengubah perilaku bisnis (aturan status, kode respons) tanpa memperbarui `docs/WEBHOOK.md`, `DECISIONS.md`, dan test-nya.
