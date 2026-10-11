<?php

namespace App\Support;

/** PKCE (RFC 7636), hanya metode S256. */
final class Pkce
{
    public static function valid(string $verifier): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier);
    }

    public static function tantangan(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function cocok(string $verifier, string $tantangan): bool
    {
        return self::valid($verifier) && hash_equals($tantangan, self::tantangan($verifier));
    }
}
