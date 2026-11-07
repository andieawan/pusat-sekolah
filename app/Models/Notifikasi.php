<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemberitahuan untuk admin Pusat (mis. "Jurnal selesai sinkron"). Satu kotak masuk bersama untuk semua superadmin. */
class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    protected $fillable = ['aplikasi_id', 'jenis', 'judul', 'isi', 'dibaca_pada'];

    protected function casts(): array
    {
        return ['dibaca_pada' => 'datetime'];
    }

    public function aplikasi(): BelongsTo
    {
        return $this->belongsTo(Aplikasi::class);
    }

    public static function akhir(): int
    {
        try {
            return (int) static::max('id');
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function belumDibaca(): int
    {
        try {
            return static::whereNull('dibaca_pada')->count();
        } catch (\Throwable) {
            return 0; // migrasi belum dijalankan
        }
    }
}
