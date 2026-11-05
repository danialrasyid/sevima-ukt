<?php

namespace App\Models\Concerns;

use App\Models\Institusi;
use App\Models\Scopes\InstitusiScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

trait BelongsToInstitusi
{
    public static function bootBelongsToInstitusi(): void
    {
        static::addGlobalScope(new InstitusiScope());

        // Pertahanan kedua: pengguna login tidak boleh membuat data untuk institusi lain.
        static::creating(function ($model) {
            $user = Auth::user();

            if ($user === null) {
                // Konteks sistem (webhook, seeder, scheduler): institusi_id diisi eksplisit oleh pemanggil.
                return;
            }

            if ($user->institusi_id === null) {
                throw new LogicException('Pengguna tanpa institusi tidak boleh membuat data.');
            }

            if ($model->institusi_id === null) {
                $model->institusi_id = $user->institusi_id;

                return;
            }

            if ((int) $model->institusi_id !== (int) $user->institusi_id) {
                throw new LogicException('Tidak boleh membuat data untuk institusi lain.');
            }
        });
    }

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class);
    }
}
