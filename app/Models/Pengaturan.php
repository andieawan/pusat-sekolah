<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Pengaturan kunci-nilai Pusat (profil sekolah, aturan password). */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $fillable = ['kunci', 'nilai'];

    private static ?array $cache = null;

    public static function semua(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = Schema::hasTable('pengaturan') ? static::pluck('nilai', 'kunci')->all() : [];
            } catch (\Throwable) {
                self::$cache = [];
            }
        }

        return self::$cache;
    }

    public static function ambil(string $kunci, ?string $bawaan = null): ?string
    {
        $v = self::semua()[$kunci] ?? null;

        return $v === null || $v === '' ? $bawaan : $v;
    }

    public static function simpan(string $kunci, ?string $nilai): void
    {
        static::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        self::$cache = null;
    }

    public static function lupakan(): void
    {
        self::$cache = null;
    }
}
