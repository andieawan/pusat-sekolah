<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Satu sambungan antar-aplikasi. "pemberi" = aplikasi yang menyediakan API, "peminta" = aplikasi yang memanggilnya.
 * Daftar jenis yang dikenal ada di config('pusat.sambungan').
 */
class Sambungan extends Model
{
    protected $table = 'sambungan';

    protected $fillable = ['jenis', 'aktif'];

    protected $hidden = ['kunci'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'kunci' => 'encrypted', 'kunci_dibuat_pada' => 'datetime'];
    }

    /** @return array{nama:string,keterangan:string,pemberi:string,peminta:string,kunci:bool}|null */
    public static function definisi(string $jenis): ?array
    {
        return config('pusat.sambungan.'.$jenis);
    }

    public function buatKunci(): void
    {
        $this->kunci = 'sk_'.Str::lower(Str::random(45));
        $this->kunci_dibuat_pada = now();
    }
}
