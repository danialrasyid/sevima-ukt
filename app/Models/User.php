<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_ADMIN_KEUANGAN = 'admin_keuangan';

    public const ROLE_MAHASISWA = 'mahasiswa';

    protected $fillable = [
        'name',
        'email',
        'password',
        'institusi_id',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class);
    }

    public function mahasiswa(): HasOne
    {
        return $this->hasOne(Mahasiswa::class);
    }

    public function isAdminKeuangan(): bool
    {
        return $this->role === self::ROLE_ADMIN_KEUANGAN;
    }

    public function homeUrl(): string
    {
        return $this->isAdminKeuangan() ? '/admin/tagihan' : '/mahasiswa/tagihan';
    }
}
