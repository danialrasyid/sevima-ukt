<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Tagihan;
use App\Support\TagihanPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Endpoint baca status tagihan + riwayat transaksi (autentikasi sesi).
 * Route model binding sudah dibatasi institusi oleh InstitusiScope (institusi lain -> 404),
 * lalu policy membatasi mahasiswa hanya ke miliknya sendiri (-> 403).
 */
class TagihanStatusController extends Controller
{
    public function show(Request $request, Tagihan $tagihan): JsonResponse
    {
        Gate::authorize('view', $tagihan);

        $tagihan->load(['mahasiswa:id,nim,nama', 'transaksi']);

        return response()->json([
            'data' => TagihanPresenter::tagihan($tagihan, withRiwayat: true, isAdmin: $request->user()->isAdminKeuangan()),
        ]);
    }

    public function byMahasiswa(Request $request, Mahasiswa $mahasiswa): JsonResponse
    {
        Gate::authorize('view', $mahasiswa);

        $isAdmin = $request->user()->isAdminKeuangan();

        $tagihan = $mahasiswa->tagihan()
            ->with('transaksi')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Tagihan $t) => TagihanPresenter::tagihan($t, withRiwayat: true, isAdmin: $isAdmin))
            ->values();

        return response()->json([
            'data' => [
                'mahasiswa' => ['id' => $mahasiswa->id, 'nim' => $mahasiswa->nim, 'nama' => $mahasiswa->nama],
                'bisa_isi_krs' => $mahasiswa->bisaIsiKrs(),
                'tagihan' => $tagihan,
            ],
        ]);
    }
}
