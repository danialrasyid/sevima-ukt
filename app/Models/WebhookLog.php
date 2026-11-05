<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log audit notifikasi masuk. Sengaja TANPA global scope institusi:
 * baris dibuat sebelum institusi terverifikasi (institusi_id bisa null).
 * Tidak ditampilkan di UI mana pun.
 */
class WebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'institusi_id',
        'kode_institusi',
        'event_id',
        'event_type',
        'nomor_tagihan',
        'signature_header',
        'signature_valid',
        'outcome',
        'http_status',
        'catatan',
        'headers',
        'raw_body',
        'ip',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'headers' => 'array',
            'received_at' => 'datetime',
        ];
    }
}
