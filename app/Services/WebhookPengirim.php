<?php

namespace App\Services;

use App\Models\WebhookOutbox;
use App\Support\Backoff;
use App\Support\Tanda;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookPengirim
{
    /**
     * Kirim satu entri antrean. Mengembalikan true bila penerima membalas 2xx.
     * Penerima WAJIB idempoten: header X-Pusat-Delivery sama untuk percobaan ulang.
     */
    public function kirim(WebhookOutbox $o): bool
    {
        $app = $o->aplikasi;
        if (! $app || ! $app->aktif || ! $app->webhook_url || ! $app->webhook_secret) {
            $o->update([
                'status' => 'gagal',
                'galat_terakhir' => 'Aplikasi nonaktif, atau URL/rahasia webhook belum diisi.',
                'jadwal_berikutnya' => null,
            ]);

            return false;
        }

        $badan = json_encode($o->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $waktu = time();
        $kode = null;
        $galat = null;

        try {
            $res = Http::withHeaders([
                'X-Pusat-Event' => $o->event,
                'X-Pusat-Delivery' => $o->uuid,
                'X-Pusat-Timestamp' => (string) $waktu,
                'X-Pusat-Signature' => Tanda::buat($app->webhook_secret, $waktu, $badan),
                'User-Agent' => 'PusatSekolah-Webhook/1.0',
            ])
                ->withBody($badan, 'application/json')
                ->withOptions(['verify' => config('pusat.webhook.ca_bundle') ?: (bool) config('pusat.webhook.verify_ssl')])
                ->timeout((int) config('pusat.webhook.timeout'))
                ->connectTimeout((int) config('pusat.webhook.connect_timeout'))
                ->post($app->webhook_url);
            $kode = $res->status();
            $ok = $res->successful();
            $galat = $ok ? null : 'HTTP '.$kode;
        } catch (Throwable $e) {
            $ok = false;
            $galat = mb_substr($e->getMessage(), 0, 500);
        }

        $percobaan = $o->percobaan + 1;
        if ($ok) {
            $o->update([
                'status' => 'terkirim', 'percobaan' => $percobaan, 'kode_http' => $kode,
                'galat_terakhir' => null, 'terkirim_pada' => now(), 'jadwal_berikutnya' => null,
            ]);

            return true;
        }

        $jeda = Backoff::detik($percobaan);
        $o->update([
            'status' => $jeda === null ? 'gagal' : 'pending',
            'percobaan' => $percobaan,
            'kode_http' => $kode,
            'galat_terakhir' => $galat,
            'jadwal_berikutnya' => $jeda === null ? null : now()->addSeconds($jeda),
        ]);

        return false;
    }

    /**
     * Kirim semua entri yang jatuh tempo. Setiap entri "diklaim" secara atomik dulu
     * agar dua proses yang berjalan bersamaan tidak mengirim ganda.
     *
     * @return array{dikirim:int, gagal:int}
     */
    public function kirimJatuhTempo(?int $batas = null): array
    {
        $batas ??= (int) config('pusat.webhook.batch');
        $hasil = ['dikirim' => 0, 'gagal' => 0];

        $ids = WebhookOutbox::where('status', 'pending')
            ->where('jadwal_berikutnya', '<=', now())
            ->orderBy('id')
            ->limit($batas)
            ->pluck('id');

        foreach ($ids as $id) {
            $klaim = WebhookOutbox::where('id', $id)
                ->where('status', 'pending')
                ->where('jadwal_berikutnya', '<=', now())
                ->update(['jadwal_berikutnya' => now()->addMinutes(2)]);
            if ($klaim !== 1) {
                continue;
            }
            $this->kirim(WebhookOutbox::with('aplikasi')->find($id)) ? $hasil['dikirim']++ : $hasil['gagal']++;
        }

        return $hasil;
    }
}
