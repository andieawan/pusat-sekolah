<?php

namespace App\Support;

/** Pemeriksaan redirect URI: harus persis sama dengan yang didaftarkan (anti open-redirect). */
final class RedirectUri
{
    public static function sahBentuk(string $uri): bool
    {
        $p = parse_url($uri);

        return is_array($p)
            && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)
            && ! empty($p['host'])
            && ! isset($p['fragment'])
            && ! isset($p['user'])
            && ! isset($p['pass']);
    }

    /** @param array<int,string> $terdaftar */
    public static function cocok(string $uri, array $terdaftar): bool
    {
        return self::sahBentuk($uri) && in_array($uri, $terdaftar, true);
    }

    /** Asal (skema://host:port) dari sebuah URL, atau null jika tidak sah. */
    public static function asal(string $uri): ?string
    {
        if (! self::sahBentuk($uri)) {
            return null;
        }
        $p = parse_url($uri);

        return strtolower($p['scheme']).'://'.strtolower($p['host']).(isset($p['port']) ? ':'.$p['port'] : '');
    }

    /** Untuk redirect setelah logout: cukup satu asal dengan salah satu URI terdaftar. */
    public static function seAsal(string $uri, array $terdaftar): bool
    {
        $a = self::asal($uri);
        if ($a === null) {
            return false;
        }
        foreach ($terdaftar as $t) {
            if (self::asal((string) $t) === $a) {
                return true;
            }
        }

        return false;
    }

    public static function tambahQuery(string $uri, array $param): string
    {
        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query($param);
    }
}
