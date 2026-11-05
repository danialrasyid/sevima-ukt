<?php

namespace App\Support;

use App\Models\Tagihan;
use App\Models\Transaksi;

/**
 * Satu-satunya tempat yang menentukan field mana dari tagihan/transaksi yang
 * boleh keluar ke UI atau API. Mahasiswa tidak menerima payload mentah / event_id.
 */
final class TagihanPresenter
{
    public static function tagihan(Tagihan $t, bool $withRiwayat = false, bool $isAdmin = false): array
    {
        $data = [
            'id' => $t->id,
            'nomor_tagihan' => $t->nomor_tagihan,
            'semester' => $t->semester,
            'nominal' => $t->nominal,
            'tanggal_kedaluwarsa' => $t->tanggal_kedaluwarsa?->toIso8601String(),
            'status' => $t->status,
            'status_efektif' => $t->status_efektif,
            'paid_at' => $t->paid_at?->toIso8601String(),
        ];

        if ($isAdmin) {
            $data['perlu_review'] = $t->perlu_review;
            $data['catatan_review'] = $t->catatan_review;
        }

        if ($t->relationLoaded('mahasiswa') && $t->mahasiswa !== null) {
            $data['mahasiswa'] = [
                'id' => $t->mahasiswa->id,
                'nim' => $t->mahasiswa->nim,
                'nama' => $t->mahasiswa->nama,
            ];
        }

        if ($withRiwayat) {
            $data['riwayat'] = $t->transaksi
                ->map(fn (Transaksi $x) => self::transaksi($x, $isAdmin))
                ->values()
                ->all();
        }

        return $data;
    }

    public static function transaksi(Transaksi $x, bool $isAdmin = false): array
    {
        $data = [
            'id' => $x->id,
            'event_type' => $x->event_type,
            'hasil' => $x->hasil,
            'keterangan' => $x->keterangan,
            'nominal' => $x->nominal,
            'status_sebelum' => $x->status_sebelum,
            'status_sesudah' => $x->status_sesudah,
            'occurred_at' => $x->occurred_at?->toIso8601String(),
        ];

        if ($isAdmin) {
            $data['event_id'] = $x->event_id;
            $data['payload'] = $x->payload;
        }

        return $data;
    }
}
