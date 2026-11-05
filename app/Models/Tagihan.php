<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitusi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tagihan extends Model
{
    use BelongsToInstitusi;

    public const BELUM_BAYAR = 'belum_bayar';

    public const LUNAS = 'lunas';

    public const KEDALUWARSA = 'kedaluwarsa';

    protected $table = 'tagihan';

    protected $fillable = [
        'institusi_id',
        'mahasiswa_id',
        'nomor_tagihan',
        'semester',
        'nominal',
        'tanggal_kedaluwarsa',
        'status',
        'paid_at',
        'perlu_review',
        'catatan_review',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'tanggal_kedaluwarsa' => 'datetime',
            'paid_at' => 'datetime',
            'perlu_review' => 'boolean',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * Status yang mencerminkan kondisi sebenarnya: tagihan belum bayar yang
     * sudah lewat batas waktu dianggap kedaluwarsa, walau command
     * `tagihan:expire` belum sempat menyimpannya ke database.
     */
    protected function statusEfektif(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->status === self::BELUM_BAYAR
                && $this->tanggal_kedaluwarsa !== null
                && $this->tanggal_kedaluwarsa->isPast()) {
                return self::KEDALUWARSA;
            }

            return $this->status;
        });
    }

    /**
     * Filter berdasarkan status efektif (konsisten dengan accessor di atas).
     */
    public function scopeStatusEfektif(Builder $query, string $status): Builder
    {
        $sekarang = now();

        return match ($status) {
            self::LUNAS => $query->where('status', self::LUNAS),
            self::BELUM_BAYAR => $query->where('status', self::BELUM_BAYAR)
                ->where('tanggal_kedaluwarsa', '>', $sekarang),
            self::KEDALUWARSA => $query->where(function (Builder $w) use ($sekarang) {
                $w->where('status', self::KEDALUWARSA)
                    ->orWhere(function (Builder $x) use ($sekarang) {
                        $x->where('status', self::BELUM_BAYAR)
                            ->where('tanggal_kedaluwarsa', '<=', $sekarang);
                    });
            }),
            default => $query,
        };
    }
}
