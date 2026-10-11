<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SsoToken extends Model
{
    protected $table = 'sso_token';

    public $timestamps = false;

    protected $fillable = ['token_hash', 'user_id', 'aplikasi_id', 'kedaluwarsa_pada', 'dicabut_pada', 'created_at'];

    protected function casts(): array
    {
        return ['kedaluwarsa_pada' => 'datetime', 'dicabut_pada' => 'datetime', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class, 'aplikasi_id');
    }
}
