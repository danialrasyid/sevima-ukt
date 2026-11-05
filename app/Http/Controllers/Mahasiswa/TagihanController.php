<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Tagihan;
use App\Support\TagihanPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagihanController extends Controller
{
    public function index(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswa;

        abort_if($mahasiswa === null, 403, 'Akun Anda belum terhubung ke data mahasiswa.');

        $tagihan = Tagihan::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->with('transaksi')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Tagihan $t) => TagihanPresenter::tagihan($t, withRiwayat: true))
            ->values();

        return Inertia::render('Mahasiswa/Tagihan', [
            'mahasiswa' => ['nim' => $mahasiswa->nim, 'nama' => $mahasiswa->nama],
            'tagihan' => $tagihan,
            'bisaIsiKrs' => $mahasiswa->bisaIsiKrs(),
        ]);
    }
}
