<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagihanRequest;
use App\Models\Mahasiswa;
use App\Models\Tagihan;
use App\Support\TagihanPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagihanController extends Controller
{
    private const STATUS = [Tagihan::BELUM_BAYAR, Tagihan::LUNAS, Tagihan::KEDALUWARSA];

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $status = in_array($status, self::STATUS, true) ? $status : null;
        $q = trim((string) $request->query('q', ''));

        // Semua query di bawah otomatis dibatasi institusi admin (InstitusiScope).
        $tagihan = Tagihan::query()
            ->with('mahasiswa:id,nim,nama')
            ->when($status, fn ($query) => $query->statusEfektif($status))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';

                $query->where(function ($w) use ($like) {
                    $w->where('nomor_tagihan', 'ilike', $like)
                        ->orWhereHas('mahasiswa', fn ($m) => $m->where('nama', 'ilike', $like)->orWhere('nim', 'ilike', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Tagihan $t) => TagihanPresenter::tagihan($t, isAdmin: true));

        return Inertia::render('Admin/Tagihan/Index', [
            'tagihan' => $tagihan,
            'mahasiswa' => Mahasiswa::query()->orderBy('nama')->get(['id', 'nim', 'nama']),
            'filters' => ['status' => $status, 'q' => $q !== '' ? $q : null],
            'statusOptions' => [
                ['value' => '', 'label' => 'Semua status'],
                ['value' => Tagihan::BELUM_BAYAR, 'label' => 'Belum bayar'],
                ['value' => Tagihan::LUNAS, 'label' => 'Lunas'],
                ['value' => Tagihan::KEDALUWARSA, 'label' => 'Kedaluwarsa'],
            ],
        ]);
    }

    public function store(StoreTagihanRequest $request): RedirectResponse
    {
        Tagihan::create([
            ...$request->validated(),
            'institusi_id' => $request->user()->institusi_id,
            'status' => Tagihan::BELUM_BAYAR,
        ]);

        return back()->with('success', 'Tagihan berhasil dibuat.');
    }
}
