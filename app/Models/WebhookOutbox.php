<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookOutbox extends Model
{
    use PunyaUuid;

    protected $table = 'webhook_outbox';

    protected $fillable = [
        'aplikasi_id', 'event', 'payload', 'status', 'percobaan',
        'jadwal_berikutnya', 'kode_http', 'galat_terakhir', 'terkirim_pada',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'jadwal_berikutnya' => 'datetime',
            'terkirim_pada' => 'datetime',
        ];
    }

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class, 'aplikasi_id');
    }
}
