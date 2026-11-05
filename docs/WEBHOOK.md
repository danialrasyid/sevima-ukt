# Dokumentasi Webhook Pembayaran

`POST /api/webhooks/payment`

Menerima notifikasi pembayaran dari payment gateway. Tanpa sesi/CSRF; autentikasi memakai signature HMAC-SHA256 dengan **secret milik institusi** yang disebut di payload.

> **Catatan:** format payload di bawah adalah asumsi berdasarkan dokumen tes. Samakan dengan dokumentasi mock gateway panitia; penyesuaian dilakukan di `app/Support/PaymentPayload.php`.

## Header

| Header | Wajib | Keterangan |
|---|---|---|
| `Content-Type` | ya | `application/json` |
| `X-Signature` | ya | Hex HMAC-SHA256 dari **body mentah** (byte persis seperti dikirim) dengan secret institusi. Awalan `sha256=` diperbolehkan. |
| `X-Institusi-Kode` | tidak | Alternatif bila kode institusi tidak ada di body. |

## Payload

```json
{
  "event_id": "evt_7f3a9c",
  "event": "payment.success",
  "institusi": "UNIV-A",
  "nomor_tagihan": "UKT-2026-001",
  "amount": 2500000,
  "paid_at": "2026-11-03T10:15:00Z"
}
```

| Field | Wajib | Keterangan |
|---|---|---|
| `event` | ya | `payment.success` atau `payment.expired` |
| `institusi` | ya | Kode institusi (menentukan secret yang dipakai verifikasi) |
| `nomor_tagihan` | ya | Dicari **hanya di dalam institusi tersebut** |
| `event_id` | disarankan | Kunci idempotensi per institusi. Jika kosong, hash body mentah dipakai |
| `amount` | tidak | Jika ada pada `payment.success` dan tidak sama dengan nominal tagihan → tidak melunasi, ditandai review |
| `paid_at` | tidak | ISO 8601 / epoch; default waktu terima |

## Respons

| HTTP | `code` / `result` | Arti | Gateway sebaiknya |
|---|---|---|---|
| 200 | `result: processed` | Event diterapkan | berhenti kirim |
| 200 | `result: duplicate` | `event_id` sudah pernah diproses; tidak ada perubahan | berhenti kirim |
| 200 | `result: ignored` | Event sah tetapi tidak mengubah status (mis. expired setelah lunas, pembayaran ganda) | berhenti kirim |
| 200 | `result: flagged` | Nominal tidak sesuai; status tidak berubah, perlu ditinjau admin | berhenti kirim |
| 400 | `payload_tidak_valid` | Body bukan JSON object | perbaiki |
| 401 | `signature_tidak_valid` | Signature salah/kosong atau institusi tidak dikenal (pesan sama agar daftar institusi tidak bocor) | periksa secret |
| 404 | `tagihan_tidak_ditemukan` | Tidak ada tagihan dengan nomor tsb di institusi pengirim | periksa data |
| 422 | `payload_tidak_valid` | Field wajib hilang / event tidak dikenal / amount bukan angka | perbaiki |
| 500 | `kesalahan_internal` | Kesalahan server | **kirim ulang** (aman karena idempoten) |

Contoh respons sukses:

```json
{
  "status": "ok",
  "result": "processed",
  "message": "Notifikasi diproses.",
  "tagihan": { "nomor_tagihan": "UKT-2026-001", "status": "lunas" }
}
```

Contoh respons galat:

```json
{ "status": "error", "code": "signature_tidak_valid", "message": "Signature tidak valid atau institusi tidak dikenal." }
```

## Aturan perubahan status

| Status sekarang | Event | Hasil |
|---|---|---|
| belum_bayar | `payment.success` (nominal cocok / tidak ada) | → **lunas** |
| belum_bayar | `payment.expired` | → **kedaluwarsa** |
| kedaluwarsa | `payment.success` | → **lunas** (uang benar-benar diterima; dicatat di riwayat) |
| lunas | `payment.expired` | tetap lunas (dicatat, `ignored`) |
| lunas | `payment.success` dengan `event_id` baru | tetap lunas, ditandai "perlu review" (`ignored`, kemungkinan pembayaran ganda) |
| apa pun | `payment.success` dengan nominal berbeda | status tidak berubah, ditandai "perlu review" (`flagged`) |
| apa pun | `event_id` yang sama dikirim ulang | tidak ada perubahan (`duplicate`) |

Semua notifikasi, **termasuk yang ditolak**, dicatat di tabel `webhook_logs` (body mentah, header terpilih, hasil verifikasi signature, outcome, HTTP status) untuk audit.

## Contoh `curl` dengan signature

```bash
BODY='{"event_id":"evt_001","event":"payment.success","institusi":"UNIV-A","nomor_tagihan":"UKT-2026-001","amount":2500000,"paid_at":"2026-11-03T10:15:00Z"}'
SECRET='isi-dengan-HMAC_SECRET_UNIV_A'

SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -hex | sed 's/^.* //')

curl -i -X POST http://localhost:8000/api/webhooks/payment \
  -H 'Content-Type: application/json' \
  -H "X-Signature: $SIG" \
  --data-binary "$BODY"
```

Kirim perintah yang sama dua kali untuk melihat respons `duplicate`. Ubah satu karakter pada `BODY` setelah menghitung `SIG` untuk melihat respons `401`.
