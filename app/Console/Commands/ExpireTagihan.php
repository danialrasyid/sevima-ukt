<?php

namespace App\Console\Commands;

use App\Models\Tagihan;
use App\Models\Transaksi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireTagihan extends Command
{
    protected $signature = 'tagihan:expire';

    protected $description = 'Ubah tagihan belum bayar yang sudah melewati tanggal kedaluwarsa menjadi kedaluwarsa';

    public function handle(): int
    {
        $jumlah = 0;

        // Konteks sistem (console): lintas institusi, tidak ada global scope.
        Tagihan::withoutGlobalScopes()
            ->where('status', Tagihan::BELUM_BAYAR)
            ->where('tanggal_kedaluwarsa', '<=', now())
            ->orderBy('id')
            ->chunkById(200, function ($daftar) use (&$jumlah) {
                foreach ($daftar as $item) {
                    DB::transaction(function () use ($item, &$jumlah) {
                        // Kunci baris + cek ulang: webhook pembayaran mungkin baru saja masuk.
                        $tagihan = Tagihan::withoutGlobalScopes()->whereKey($item->id)->lockForUpdate()->first();

                        if ($tagihan === null
                            || $tagihan->status !== Tagihan::BELUM_BAYAR
                            || $tagihan->tanggal_kedaluwarsa->isFuture()) {
                            return;
                        }

                        $tagihan->update(['status' => Tagihan::KEDALUWARSA]);

                        Transaksi::create([
                            'institusi_id' => $tagihan->institusi_id,
                            'tagihan_id' => $tagihan->id,
                            'event_id' => 'system-expire-'.$tagihan->id,
                            'event_type' => 'system.expired',
                            'status_sebelum' => Tagihan::BELUM_BAYAR,
                            'status_sesudah' => Tagihan::KEDALUWARSA,
                            'hasil' => Transaksi::HASIL_DITERAPKAN,
                            'keterangan' => 'Kedaluwarsa otomatis: batas waktu pembayaran terlewati.',
                            'occurred_at' => now(),
                        ]);

                        $jumlah++;
                    });
                }
            });

        $this->info("{$jumlah} tagihan diubah menjadi kedaluwarsa.");

        return self::SUCCESS;
    }
}
