# DECISIONS

Lima keputusan teknis penting. Baca dan sesuaikan dengan alasan Anda sendiri sebelum dikumpulkan; Anda harus bisa menjelaskan tiap poin di wawancara.

## Isolasi data antar-institusi berlapis

Konteks : Banyak kampus berbagi satu database. Kebocoran data antar-kampus adalah risiko terbesar, dan satu `where` yang terlupa sudah cukup untuk membocorkannya.
Opsi : (1) filter `institusi_id` manual di setiap query; (2) global scope Eloquent; (3) database terpisah / schema per kampus; (4) PostgreSQL Row Level Security.
Keputusan : Global scope `InstitusiScope` yang *fail-closed* (pengguna login: dibatasi ke institusinya; request HTTP tanpa pengguna: tidak ada data; hanya konteks console yang tanpa batas), ditambah hook `creating` yang menolak pembuatan data lintas institusi, **foreign key komposit** `(institusi_id, mahasiswa_id)` / `(institusi_id, tagihan_id)` di database, serta `Rule::exists/unique` yang dibatasi institusi pada validasi. Webhook (tanpa pengguna) wajib memakai `withoutGlobalScopes()` + `where('institusi_id')` eksplisit.
Konsekuensi : Developer tidak bisa lupa scope pada query biasa, dan database sendiri menolak relasi lintas institusi. Harganya: kode sistem (webhook, seeder, scheduler) harus eksplisit soal institusi, dan RLS PostgreSQL tidak dipakai, jadi query mentah (`DB::table`) tidak terlindungi scope, sehingga aturan itu ditulis di CLAUDE.md.

## Idempotensi dan konkurensi webhook

Konteks : Gateway dunia nyata mengirim ulang notifikasi (retry), kadang dua kali hampir bersamaan, dan bisa membalas 5xx padahal data sudah tersimpan.
Opsi : (1) cek-lalu-ubah tanpa kunci; (2) hanya unique constraint; (3) kunci baris tagihan + cek duplikat + unique constraint; (4) antrean (queue) per tagihan.
Keputusan : Dalam satu transaksi database: `SELECT ... FOR UPDATE` pada baris tagihan, cek `event_id` pada `transaksi`, terapkan perubahan, simpan transaksi. Unique `(institusi_id, event_id)` menjadi jaring pengaman terakhir; pelanggarannya ditangkap dan diperlakukan sebagai duplikat. Respons duplikat adalah 200 agar gateway berhenti mengirim ulang. Jika gateway tidak mengirim `event_id`, hash body mentah dipakai.
Konsekuensi : Retry dan balapan aman, tidak ada pembayaran terhitung dua kali. Tiap webhook untuk tagihan yang sama diproses berurutan (cukup untuk skala tes ini). Tanpa `event_id` dari gateway, dua event berbeda dengan body identik dianggap satu.

## Aturan status untuk event tidak berurutan dan tidak ideal

Konteks : Dokumen tes menegaskan "status harus mencerminkan kondisi pembayaran yang sebenarnya" dan bahwa perilaku dunia nyata tidak selalu ideal (event terlambat, tidak berurutan, nominal janggal).
Opsi : (1) status terakhir selalu menang; (2) status final tidak pernah berubah; (3) state machine berbasis fakta pembayaran.
Keputusan : `payment.success` selalu melunasi (uang benar-benar diterima), termasuk bila tagihan sudah kedaluwarsa; `payment.expired` tidak pernah menurunkan tagihan lunas (dicatat, diabaikan); `payment.success` dengan nominal berbeda tidak melunasi melainkan ditandai "perlu review"; `payment.success` kedua dengan `event_id` berbeda pada tagihan lunas dianggap pembayaran ganda: status tetap, ditandai review. Setiap kasus tetap tercatat sebagai transaksi dengan `hasil` (diterapkan / diabaikan / ditandai).
Konsekuensi : Mahasiswa yang sudah membayar tidak pernah dirugikan oleh event terlambat, dan anomali uang tidak hilang diam-diam. Admin perlu meninjau tagihan bertanda review (belum ada alur penyelesaian atau refund di aplikasi ini).

## Verifikasi signature dan penyimpanan secret

Konteks : Endpoint webhook publik; siapa pun bisa mengirim request. Tiap institusi punya secret sendiri, dan secret tidak boleh ada di repositori.
Opsi : (1) satu secret global; (2) secret per institusi di `.env`; (3) secret per institusi di database terenkripsi.
Keputusan : Secret per institusi disimpan di kolom `webhook_secret` dengan cast `encrypted` (dienkripsi `APP_KEY`), diisi seeder dari variabel `.env` (`HMAC_SECRET_<KODE>`), dan di-`$hidden` agar tidak terserialisasi. HMAC-SHA256 dihitung dari **body mentah**, dibandingkan dengan `hash_equals`. Institusi ditentukan dari payload sebelum verifikasi, tetapi payload tidak dipercaya sampai signature valid. Institusi tidak dikenal dan signature salah menghasilkan respons 401 yang sama.
Konsekuensi : Secret satu kampus tidak bisa memvalidasi payload kampus lain (ada test-nya), dan dump database tanpa `APP_KEY` tidak membuka secret. Rotasi secret berarti mengubah `.env` dan menjalankan ulang seeder; mengganti `APP_KEY` membuat secret lama tidak terbaca. Perlindungan replay berbasis timestamp belum ada (tergantung format mock gateway).

## Penanganan kedaluwarsa: command terjadwal plus status efektif

Konteks : Tagihan yang melewati tanggal kedaluwarsa harus tampil kedaluwarsa walau gateway belum/tidak mengirim `payment.expired`.
Opsi : (1) hanya mengandalkan event gateway; (2) hanya menghitung saat dibaca (accessor); (3) command terjadwal yang menyimpan status; (4) kombinasi.
Keputusan : Kombinasi: command `tagihan:expire` (tiap menit) menyimpan status kedaluwarsa dan mencatat transaksi `system.expired` dengan mengunci baris dan memeriksa ulang; selain itu accessor `status_efektif` dan scope `statusEfektif()` dipakai UI dan filter agar tampilan benar walau command belum sempat berjalan. Webhook `payment.success` yang datang kemudian tetap melunasi.
Konsekuensi : Tampilan akurat setiap saat dan data tersimpan konsisten untuk laporan. Ada dua representasi status (tersimpan vs efektif) yang harus dijaga konsisten (dijaga oleh test), dan butuh proses scheduler yang berjalan (service `scheduler` di docker compose).
