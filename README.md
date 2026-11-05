# Tagihan UKT: Integrasi Pembayaran via Webhook (SEVIMA, Project M2)

Aplikasi web fullstack untuk mengelola tagihan UKT multi-kampus dan menerima notifikasi pembayaran dari payment gateway (mock) lewat webhook bertanda tangan HMAC.

- **Stack:** Laravel (Inertia + Vue 3, tanpa Livewire), Tailwind CSS, PostgreSQL 16
- **Role:** admin keuangan (daftar tagihan, filter status, buat tagihan, detail riwayat) dan mahasiswa (status tagihan + riwayat transaksi)
- **Dokumen lain:** [docs/WEBHOOK.md](docs/WEBHOOK.md) · [DECISIONS.md](DECISIONS.md) · [CLAUDE.md](CLAUDE.md) · [AI_NOTES.md](AI_NOTES.md)

## Menjalankan dengan Docker Compose (disarankan)

Prasyarat: Docker dan Docker Compose.

```bash
cp .env.example .env
```

Edit `.env`:

1. `DB_PASSWORD`: isi bebas (dipakai container PostgreSQL dan aplikasi).
2. `APP_KEY`: isi hasil `openssl rand -base64 32` dengan format `APP_KEY=base64:<hasil>`. (Jika dikosongkan, container membuatnya otomatis.)
3. `HMAC_SECRET_UNIV_A`, `HMAC_SECRET_UNIV_B`, `HMAC_SECRET_UNIV_C`: secret HMAC dari email panitia.
4. Pastikan `kode` institusi di `config/sevima.php` sama dengan kode di seed mock gateway. Jika berbeda, ubah kodenya **dan** nama variabel `HMAC_SECRET_<KODE>` di `.env`.

```bash
docker compose up --build
```

Saat start, container `app` otomatis menjalankan migrasi dan seeder (idempotent). Container `scheduler` menjalankan `tagihan:expire` setiap menit.

Buka http://localhost:8000.

### Akun demo (dibuat seeder; password dari `DEMO_PASSWORD`, default `password`)

| Role | Email | Institusi |
|---|---|---|
| Admin keuangan | `admin.a@example.test` | UNIV-A |
| Admin keuangan | `admin.b@example.test` | UNIV-B |
| Admin keuangan | `admin.c@example.test` | UNIV-C |
| Mahasiswa | `mhs1.a@example.test` … `mhs3.a@example.test` | UNIV-A |
| Mahasiswa | `mhs1.b@example.test` … `mhs3.b@example.test` | UNIV-B |
| Mahasiswa | `mhs1.c@example.test` … `mhs3.c@example.test` | UNIV-C |

Tiap mahasiswa punya satu tagihan seed `UKT-2026-001` … `UKT-2026-003` (Rp 2.500.000, belum bayar). Nomor tagihan sengaja sama di setiap institusi untuk menunjukkan keunikan per institusi.

### Test otomatis

```bash
docker compose exec app php artisan test
```

Test memakai database terpisah `sevima_ukt_test` (dibuat otomatis saat volume pertama kali dibuat). Bila volume sudah ada sebelumnya:

```bash
docker compose exec db psql -U sevima -d sevima_ukt -c "CREATE DATABASE sevima_ukt_test"
```

## Menjalankan tanpa Docker

Prasyarat: PHP 8.3+ (ekstensi `pdo_pgsql`), Composer, Node 20+, PostgreSQL.

```bash
cp .env.example .env            # isi DB_*, HMAC_SECRET_*
composer install
php artisan key:generate
npm install && npm run build    # atau `npm run dev` untuk HMR
php artisan migrate --seed
php artisan serve               # terminal 1
php artisan schedule:work       # terminal 2 (kedaluwarsa otomatis)
```

Buat database `sevima_ukt` dan `sevima_ukt_test` terlebih dahulu, dan sesuaikan `DB_HOST`/`DB_PORT` di `.env`.

## Menguji webhook dari mock gateway

Arahkan URL webhook mock gateway ke `http://localhost:8000/api/webhooks/payment` (dari container lain: `http://host.docker.internal:8000/...`). Format payload, kode respons, dan contoh `curl` ber-signature ada di [docs/WEBHOOK.md](docs/WEBHOOK.md).

## Struktur penting

| Path | Isi |
|---|---|
| `app/Services/PaymentWebhookProcessor.php` | Seluruh alur webhook (log, HMAC, idempotensi, aturan status) |
| `app/Support/PaymentPayload.php` | Adapter format payload gateway (satu-satunya tempat yang tahu nama field) |
| `app/Models/Concerns/BelongsToInstitusi.php`, `Scopes/InstitusiScope.php` | Isolasi data antar-institusi |
| `app/Console/Commands/ExpireTagihan.php` | Kedaluwarsa otomatis (dijadwalkan tiap menit) |
| `resources/js/Pages/**` | Halaman Inertia + Vue (dialog di `components/`) |
| `tests/Feature/**` | Test isolasi institusi, webhook, kedaluwarsa, login |

## Asumsi

Dokumen tes menyebut: untuk hal ambigu, tuliskan asumsi. Berikut asumsi saya:

1. **Format payload gateway** ditebak dari dokumen tes (`event_id`, `event`, `institusi`, `nomor_tagihan`, `amount`, `paid_at`). Jika mock gateway memakai nama lain, hanya `app/Support/PaymentPayload.php` yang perlu disesuaikan.
2. **Signature** adalah hex HMAC-SHA256 dari body mentah, di header `X-Signature` (awalan `sha256=` juga diterima). Kode institusi dibaca dari payload (atau header `X-Institusi-Kode`).
3. **Idempotensi** berdasar `event_id` per institusi. Jika gateway tidak mengirim `event_id`, hash SHA-256 body mentah dipakai sebagai gantinya.
4. **Satu tagihan UKT per mahasiswa per semester** (unique di database).
5. **Nominal** adalah rupiah bulat. Event `payment.success` dengan `amount` ≠ nominal tagihan **tidak melunasi**; ditandai "perlu review".
6. **Pembayaran setelah kedaluwarsa** tetap melunasi (uang benar-benar diterima); dicatat di riwayat.
7. **KRS:** mahasiswa dianggap dapat mengisi KRS bila tagihan UKT terbarunya lunas.
8. Satu akun pengguna hanya milik satu institusi; satu akun mahasiswa terhubung ke satu data mahasiswa.
9. Waktu disimpan UTC (`timestamptz`), ditampilkan di UI dalam Asia/Jakarta.
10. Tagihan tidak ditemukan untuk institusi pengirim → HTTP 404 (dan tercatat di log audit).

## Batasan yang disadari

- Tidak ada perlindungan replay berbasis timestamp pada webhook (bergantung format mock gateway).
- Tidak ada edit/hapus tagihan, refund, atau cicilan (di luar cakupan tes).
- Log audit webhook (`webhook_logs`) belum punya halaman UI; diperiksa lewat database/log aplikasi.
