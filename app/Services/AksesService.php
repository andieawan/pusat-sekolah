<?php

namespace App\Services;

use App\Models\Akses;
use App\Models\Aplikasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AksesService
{
    public function __construct(private SsoService $sso) {}

    /**
     * Setel peran akun di semua aplikasi sekaligus.
     *
     * @param array<int,array<int,string>> $peranPerAplikasi  [aplikasi_id => [peran, ...]]
     * @return int jumlah aplikasi yang aksesnya berubah
     */
    public function sinkron(User $user, array $peranPerAplikasi): int
    {
        $berubah = [];

        DB::transaction(function () use ($user, $peranPerAplikasi, &$berubah) {
            foreach (Aplikasi::all() as $app) {
                $baru = array_values(array_unique(array_intersect(
                    (array) ($peranPerAplikasi[$app->id] ?? []),
                    $app->peran_tersedia ?? [],
                )));
                sort($baru);
                $lama = Akses::where('user_id', $user->id)->where('aplikasi_id', $app->id)->pluck('peran')->all();
                sort($lama);
                if ($baru === $lama) {
                    continue;
                }

                Akses::where('user_id', $user->id)->where('aplikasi_id', $app->id)
                    ->whereNotIn('peran', $baru)->delete();
                foreach (array_diff($baru, $lama) as $peran) {
                    Akses::create(['user_id' => $user->id, 'aplikasi_id' => $app->id, 'peran' => $peran]);
                }
                $berubah[] = [$app, $baru];
            }
        });

        foreach ($berubah as [$app, $peran]) {
            if ($peran === []) {
                $this->sso->cabutUser($user, $app->id); // akses dicabut: token aplikasi itu mati
            }
            Outbox::akses($user, $app, $peran);
            Audit::catat('akses.diubah', $user->username, ['aplikasi' => $app->kode, 'peran' => $peran]);
        }

        return count($berubah);
    }
}
