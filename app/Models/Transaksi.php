<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitusi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaksi extends Model
{
    use BelongsToInstitusi;

    public const HASIL_DITERAPKAN = 'diterapkan';

    public const HASIL_DIABAIKAN = 'diabaikan';

    public const HASIL_DITANDAI = 'ditandai';

    protected $table = 'transaksi';

    protected $fillable = [
        'institusi_id',
        'tagihan_id',
        'event_id',
        'event_type',
        'nominal',
        'status_sebelum',
        'status_sesudah',
        'hasil',
        'keterangan',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }
}
