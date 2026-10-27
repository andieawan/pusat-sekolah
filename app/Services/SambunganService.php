<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\Sambungan;
use App\Models\WebhookOutbox;

/**
 * Sambungan antar-aplikasi. Kunci TIDAK ikut di webhook: Pusat hanya mengabari "sambungan berubah",
 * lalu aplikasi mengambil sendiri lewat GET /api/v1/sambungan (Basic client_id:client_secret).
 */
final class SambunganService
{
    /** @return array<string,Aplikasi|null> [pemberi, peminta] */
    public static function pasangan(array $def): array
    {
        $app = Aplikasi::whereIn('kode', [$def['pemberi'], $def['peminta']])->get()->keyBy('kode');

        return [$app[$def['pemberi']] ?? null, $app[$def['peminta']] ?? null];
    }

    /** Kabari kedua aplikasi agar mengambil sambungan terbaru. @return WebhookOutbox[] */
    public static function kabari(string $jenis): array
    {
        $def = Sambungan::definisi($jenis);
        $antre = [];
        foreach (self::pasangan($def) as $app) {
            if ($app && $app->aktif && $app->webhook_url) {
                $antre[] = Outbox::antre($app, 'sambungan.diperbarui', ['jenis' => $jenis]);
            }
        }

        return $antre;
    }

    /** Daftar sambungan untuk satu aplikasi (dipakai API). */
    public static function untuk(Aplikasi $app): array
    {
        $baris = Sambungan::all()->keyBy('jenis');
        $hasil = [];
        foreach ((array) config('pusat.sambungan') as $jenis => $def) {
            if (! in_array($app->kode, [$def['pemberi'], $def['peminta']], true)) {
                continue;
            }
            [$pemberi, $peminta] = self::pasangan($def);
            $mitra = $app->kode === $def['pemberi'] ? $peminta : $pemberi;
            $row = $baris[$jenis] ?? null;
            $aktif = $row && $row->aktif && $mitra && $mitra->aktif;
            $hasil[] = [
                'jenis' => $jenis,
                'aktif' => $aktif,
                'peran' => $app->kode === $def['pemberi'] ? 'pemberi' : 'peminta',
                'mitra' => $mitra?->kode,
                'url' => $aktif ? rtrim((string) $mitra->base_url, '/') : '',
                'kunci' => $aktif && $def['kunci'] ? (string) $row->kunci : '',
            ];
        }

        return $hasil;
    }
}
