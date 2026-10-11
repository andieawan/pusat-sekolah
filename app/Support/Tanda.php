<?php

namespace App\Support;

/**
 * Tanda tangan HMAC untuk webhook. Penerima memverifikasi dengan rahasia yang sama.
 * Format yang ditandatangani: "{timestamp}.{badan mentah}".
 */
final class Tanda
{
    public static function buat(string $rahasia, int $waktu, string $badan): string
    {
        return 'sha256='.hash_hmac('sha256', $waktu.'.'.$badan, $rahasia);
    }

    public static function verifikasi(
        string $rahasia,
        int $waktu,
        string $badan,
        string $tanda,
        int $toleransi = 300,
        ?int $sekarang = null,
    ): bool {
        $sekarang ??= time();
        if (abs($sekarang - $waktu) > $toleransi) {
            return false; // terlalu lama/baru: cegah replay
        }

        return hash_equals(self::buat($rahasia, $waktu, $badan), $tanda);
    }
}
