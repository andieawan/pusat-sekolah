<?php

namespace App\Support;

/** Jeda kirim ulang webhook: 1 mnt, 5 mnt, 15 mnt, 1 jam, 6 jam, lalu menyerah. */
final class Backoff
{
    public const MAKS_PERCOBAAN = 6;

    private const JEDA = [60, 300, 900, 3600, 21600];

    /** Jeda (detik) setelah percobaan gagal ke-$gagal, atau null jika harus menyerah. */
    public static function detik(int $gagal): ?int
    {
        if ($gagal >= self::MAKS_PERCOBAAN) {
            return null;
        }

        return self::JEDA[max(1, min($gagal, count(self::JEDA))) - 1];
    }
}
