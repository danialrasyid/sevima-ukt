# Panduan Instalasi dan Urutan Pengerjaan

Paket ini adalah **overlay**: berkas yang ditimpakan di atas proyek Laravel baru. Ditulis untuk Laravel 12+ (Tailwind 4, Vite).

## A. Instalasi dari nol

```bash
# 1. Proyek Laravel baru
composer create-project laravel/laravel sevima-ukt
cd sevima-ukt
git init && git add -A && git commit -m "chore: init laravel"

# 2. Timpakan overlay (menimpa bootstrap/app.php, routes/*, vite.config.js, phpunit.xml,
#    tests/Feature/ExampleTest.php, database/seeders/DatabaseSeeder.php, app/Models/User.php, README.md, dll.)
unzip -o ../sevima-ukt-overlay.zip -d .

# 3. Dependensi tambahan
composer require inertiajs/inertia-laravel
npm install vue @inertiajs/vue3
npm install -D @vitejs/plugin-vue
```

### Database dan .env

```bash
cp .env.example .env
```

Isi `DB_PASSWORD`, `HMAC_SECRET_*` (dari email panitia), lalu:

```bash
php artisan key:generate
docker compose up -d db          # PostgreSQL + database test (port host 5433)
php artisan migrate --seed
```

> Tanpa Docker: buat database `sevima_ukt` dan `sevima_ukt_test` di PostgreSQL Anda, lalu sesuaikan `DB_HOST`/`DB_PORT`.

### Menjalankan

```bash
npm run build                    # atau `npm run dev` di terminal terpisah
php artisan serve                # http://localhost:8000
php artisan schedule:work        # terminal lain: kedaluwarsa otomatis
php artisan test                 # test (PostgreSQL, database sevima_ukt_test)
```

Atau seluruhnya via Docker: `docker compose up --build` (lihat README.md).

### Sebelum mulai: sesuaikan dengan mock gateway

1. Baca dokumentasi mock gateway panitia.
2. Samakan **kode institusi** di `config/sevima.php` dengan seed panitia (dan nama variabel `HMAC_SECRET_<KODE>` di `.env`).
3. Samakan **nama field payload** di `app/Support/PaymentPayload.php`. Ini satu-satunya tempat yang tahu format payload.
4. Pastikan cara hitung signature sama (hex HMAC-SHA256 dari body mentah). Jika berbeda, ubah `signatureValid()` di `PaymentWebhookProcessor` dan test-nya.

## B. Urutan commit yang disarankan (riwayat granular)

| # | Commit | Isi |
|---|---|---|
| 1 | `chore: init laravel project` | hasil `composer create-project` |
| 2 | `chore: setup inertia, vue, tailwind, vite` | `composer require`, `npm install`, `vite.config.js`, `resources/js/app.js`, `resources/css/app.css`, `resources/views/app.blade.php`, `bootstrap/app.php`, `HandleInertiaRequests` |
| 3 | `chore: docker compose postgres, app, scheduler` | `Dockerfile`, `docker-compose.yml`, `docker/`, `.dockerignore`, `.env.example`, `phpunit.xml` |
| 4 | `docs: tambah CLAUDE.md` | konteks & aturan untuk AI sebelum coding |
| 5 | `feat(db): skema institusi, mahasiswa, tagihan, transaksi, webhook_logs` | 6 migration |
| 6 | `feat(tenant): scope dan trait BelongsToInstitusi` | `Scopes/`, `Concerns/`, semua model |
| 7 | `feat(auth): login, role middleware, policy` | `LoginController`, `EnsureRole`, `Policies`, `Login.vue`, `routes/web.php` (bagian auth) |
| 8 | `feat(tagihan): halaman admin, form buat tagihan, filter status` | `StoreTagihanRequest`, `Admin\TagihanController`, `Admin/Tagihan/**`, layout, komponen |
| 9 | `feat(mahasiswa): halaman status dan riwayat, endpoint JSON` | `Mahasiswa\TagihanController`, `Api\TagihanStatusController`, `TagihanPresenter`, `Mahasiswa/Tagihan.vue` |
| 10 | `feat(webhook): endpoint, HMAC, idempotensi, aturan status` | `PaymentPayload`, `PaymentWebhookProcessor`, controller, `routes/api.php` |
| 11 | `feat(expire): command tagihan:expire dan scheduler` | `ExpireTagihan`, `routes/console.php` |
| 12 | `feat(seed): seeder tiga institusi dan akun demo` | `config/sevima.php`, `DatabaseSeeder` |
| 13 | `test: isolasi institusi, webhook, kedaluwarsa, auth` | `tests/**` (idealnya dipecah per fitur, di commit fitur terkait) |
| 14 | `docs: README, WEBHOOK, DECISIONS, AI_NOTES` | dokumen |

Lebih baik lagi: commit test **bersama** fiturnya, bukan semua di akhir.

## C. Checklist sebelum mengumpulkan

- [ ] Clone bersih → `cp .env.example .env` → isi → `docker compose up --build` → bisa login (admin & mahasiswa tiap institusi)
- [ ] Webhook dari **mock gateway sungguhan** berhasil mengubah status (bukan hanya dari `curl`)
- [ ] `php artisan test` hijau
- [ ] `git log -p | grep -i -E "secret|password"`: tidak ada secret asli di riwayat (hanya placeholder kosong di `.env.example`)
- [ ] `.env` tidak ter-commit
- [ ] `AI_NOTES.md` terisi dengan pengalaman Anda sendiri; `DECISIONS.md` sudah Anda baca dan sesuaikan
- [ ] README: akun demo per role dan asumsi sudah benar
- [ ] Repositori **privat**, akses diberikan ke: `nur.arifah@sevima.com`, `careers@sevima.com`, `alfian@sevima.com`, `mahendri@sevima.com`
- [ ] Email tautan repo ke pengirim tes, sebelum tenggat, subjek: `[Nama] TaskSE- SEVIMA`

## D. Persiapan tahap 2 (wawancara 75–90 menit)

- Latih demo end-to-end bersama mock gateway.
- Pastikan Anda bisa menjelaskan tiap keputusan di `DECISIONS.md` dan menelusuri satu webhook dari log: `webhook_logs` → `transaksi` → status tagihan.
- Latih skenario insiden: "tagihan sudah dibayar tapi status belum bayar", dengan memeriksa `outcome` di `webhook_logs` (`signature_ditolak`, `tagihan_tidak_ditemukan`, `duplikat`, dst.) dan `storage/logs/laravel.log` (baris `webhook.*`).
- Siapkan AI assistant Anda dan pikirkan fitur lanjutan yang mungkin diminta (event `payment.refunded`, halaman audit webhook, edit tagihan).
