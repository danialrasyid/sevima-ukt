<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitusi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mahasiswa extends Model
{
    use BelongsToInstitusi;

    protected $table = 'mahasiswa';

    protected $fillable = ['institusi_id', 'user_id', 'nim', 'nama', 'program_studi'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    /**
     * Asumsi: mahasiswa boleh mengisi KRS jika tagihan UKT terbarunya lunas.
     */
    public function bisaIsiKrs(): bool
    {
        $terbaru = $this->tagihan()->orderByDesc('id')->first();

        return $terbaru !== null && $terbaru->status_efektif === Tagihan::LUNAS;
    }
}
