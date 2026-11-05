<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Membatasi semua query model multi-institusi ke institusi milik pengguna login.
 *
 * Fail-closed:
 *  - pengguna login tanpa institusi        -> tidak ada data
 *  - request HTTP tanpa pengguna login      -> tidak ada data
 *  - konteks console (seeder, scheduler)    -> tanpa batasan (tidak ada "pengguna")
 *
 * Webhook adalah request HTTP tanpa pengguna, jadi WAJIB memakai
 * withoutGlobalScopes() + where('institusi_id', ...) secara eksplisit.
 */
class InstitusiScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        $column = $model->qualifyColumn('institusi_id');

        if ($user !== null) {
            if ($user->institusi_id === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($column, $user->institusi_id);

            return;
        }

        if (! app()->runningInConsole()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
