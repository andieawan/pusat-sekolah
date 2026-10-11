<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SsoKode extends Model
{
    protected $table = 'sso_kode';

    public $timestamps = false;

    protected $fillable = [
        'kode_hash', 'user_id', 'aplikasi_id', 'redirect_uri', 'code_challenge',
        'kedaluwarsa_pada', 'digunakan_pada', 'created_at',
    ];

    protected function casts(): array
    {
        return ['kedaluwarsa_pada' => 'datetime', 'digunakan_pada' => 'datetime', 'created_at' => 'datetime'];
    }
}
