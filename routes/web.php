<?php

use App\Http\Controllers\Admin\TagihanController as AdminTagihanController;
use App\Http\Controllers\Api\TagihanStatusController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Mahasiswa\TagihanController as MahasiswaTagihanController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? auth()->user()->homeUrl() : '/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Halaman admin keuangan
Route::middleware(['auth', 'role:admin_keuangan'])->prefix('admin')->group(function () {
    Route::get('/tagihan', [AdminTagihanController::class, 'index'])->name('admin.tagihan.index');
    Route::post('/tagihan', [AdminTagihanController::class, 'store'])->name('admin.tagihan.store');
});

// Halaman mahasiswa
Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->group(function () {
    Route::get('/tagihan', [MahasiswaTagihanController::class, 'index'])->name('mahasiswa.tagihan');
});

// Endpoint JSON status tagihan + riwayat transaksi (sesi login; dipakai juga oleh dialog detail)
Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/tagihan/{tagihan}', [TagihanStatusController::class, 'show'])->name('api.tagihan.show');
    Route::get('/mahasiswa/{mahasiswa}/tagihan', [TagihanStatusController::class, 'byMahasiswa'])->name('api.mahasiswa.tagihan');
});
