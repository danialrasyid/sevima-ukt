<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institusi extends Model
{
    protected $table = 'institusi';

    protected $fillable = ['kode', 'nama', 'webhook_secret'];

    // Secret tidak boleh ikut ter-serialize ke response / props Inertia.
    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'webhook_secret' => 'encrypted',
        ];
    }
}
