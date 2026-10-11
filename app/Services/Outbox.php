<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\User;
use App\Models\WebhookOutbox;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Mencatat perubahan ke antrean webhook (pola outbox). Pengiriman dilakukan terpisah oleh
 * WebhookPengirim sehingga aplikasi penerima yang mati tidak memperlambat/menggagalkan perubahan data.
 */
final class Outbox
{
    /** Antre satu event ke satu aplikasi. */
    public static function antre(Aplikasi $app, string $event, array $data): WebhookOutbox
    {
        $o = new WebhookOutbox([
            'aplikasi_id' => $app->id,
            'event' => $event,
            'status' => 'pending',
            'jadwal_berikutnya' => now(),
        ]);
        $o->uuid = (string) \Illuminate\Support\Str::uuid();
        $o->payload = [
            'id' => $o->uuid,
            'event' => $event,
            'waktu' => now()->toIso8601String(),
            'data' => $data,
        ];
        $o->save();

        return $o;
    }

    /** Event perubahan data induk: dikirim ke setiap aplikasi aktif yang berhak atas sumber tersebut. */
    public static function dari(Model $m, string $aksi): void
    {
        try {
            $sumber = $m::SUMBER;
            Aplikasi::where('aktif', true)->whereNotNull('webhook_url')->get()->each(
                function (Aplikasi $app) use ($m, $sumber, $aksi) {
                    $level = $app->aksesSumber($sumber);
                    if ($level === null) {
                        return;
                    }
                    self::antre($app, $sumber.'.'.$aksi, Representasi::dari($m, $level === 'lengkap'));
                },
            );
        } catch (Throwable $e) {
            report($e); // jangan menggagalkan perubahan data hanya karena antrean bermasalah
        }
    }

    /** Peran akun di satu aplikasi berubah (peran kosong = akses dicabut). */
    public static function akses(User $u, Aplikasi $app, array $peran): void
    {
        try {
            if ($app->aktif && $app->webhook_url && $app->punyaScope('akun')) {
                self::antre($app, 'akses.diperbarui', Representasi::akun($u, $peran));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Data akun (nama/email/aktif) berubah: kabarkan ke semua aplikasi tempat akun punya akses. */
    public static function akun(User $u): void
    {
        try {
            $u->akses()->get()->groupBy('aplikasi_id')->each(function ($baris, $aplikasiId) use ($u) {
                $app = Aplikasi::find($aplikasiId);
                if ($app && $app->aktif && $app->webhook_url && $app->punyaScope('akun')) {
                    self::antre($app, 'akun.diperbarui', Representasi::akun($u, $baris->pluck('peran')->all()));
                }
            });
        } catch (Throwable $e) {
            report($e);
        }
    }
}
