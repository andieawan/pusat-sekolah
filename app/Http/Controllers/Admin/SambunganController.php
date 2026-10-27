<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use App\Models\Sambungan;
use App\Models\WebhookOutbox;
use App\Services\Audit;
use App\Services\SambunganService;
use App\Services\WebhookPengirim;

/** Menu Administrasi → Sambungan: nyalakan/matikan sambungan antar-aplikasi tanpa menyalin kunci ke .env. */
class SambunganController extends Controller
{
    public function index()
    {
        $baris = Sambungan::all()->keyBy('jenis');
        $daftar = [];
        foreach ((array) config('pusat.sambungan') as $jenis => $def) {
            [$pemberi, $peminta] = SambunganService::pasangan($def);
            $kabar = [];
            foreach ([$pemberi, $peminta] as $app) {
                if ($app) {
                    $kabar[$app->kode] = WebhookOutbox::where('aplikasi_id', $app->id)->where('event', 'sambungan.diperbarui')
                        ->where('payload->data->jenis', $jenis)->latest('id')->first();
                }
            }
            $daftar[$jenis] = ['def' => $def, 'row' => $baris[$jenis] ?? null, 'pemberi' => $pemberi, 'peminta' => $peminta, 'kabar' => $kabar];
        }

        return view('admin.sambungan.index', ['daftar' => $daftar]);
    }

    private function ambil(string $jenis): Sambungan
    {
        abort_unless(Sambungan::definisi($jenis), 404);

        return Sambungan::firstOrNew(['jenis' => $jenis]);
    }

    /** Antre kabar ke kedua aplikasi lalu langsung kirim (tanpa menunggu penjadwal). */
    private function selesai(string $jenis, string $pesan, WebhookPengirim $pengirim)
    {
        $antre = SambunganService::kabari($jenis);
        $gagal = [];
        foreach ($antre as $o) {
            if (! $pengirim->kirim($o->load('aplikasi'))) {
                $gagal[] = $o->aplikasi->nama;
            }
        }
        if (! $antre) {
            return back()->with('sukses', $pesan.' (Belum ada aplikasi yang bisa dikabari: URL webhook kosong. Klik "Sinkron sekarang" di halaman Integrasi aplikasi itu.)');
        }
        if ($gagal) {
            return back()->with('galat', $pesan.' Tetapi '.implode(' & ', $gagal).' belum menerima kabar (aplikasi mati atau belum diperbarui). '
                .'Pusat mencoba lagi otomatis; bisa juga klik "Sinkron sekarang" di halaman Integrasi aplikasi itu.');
        }

        return back()->with('sukses', $pesan.' Kedua aplikasi sudah menerima pengaturan baru.');
    }

    public function nyalakan(string $jenis, WebhookPengirim $pengirim)
    {
        $s = $this->ambil($jenis);
        $def = Sambungan::definisi($jenis);
        [$pemberi, $peminta] = SambunganService::pasangan($def);
        if (! $pemberi || ! $peminta) {
            return back()->with('galat', 'Daftarkan dulu kedua aplikasi di menu Aplikasi (kode "'.$def['pemberi'].'" dan "'.$def['peminta'].'").');
        }
        if ($def['kunci'] && ! $s->kunci) {
            $s->buatKunci();
        }
        $s->aktif = true;
        $s->save();
        Audit::catat('sambungan.nyalakan', $jenis);

        return $this->selesai($jenis, 'Sambungan '.$def['nama'].' dinyalakan.', $pengirim);
    }

    public function matikan(string $jenis, WebhookPengirim $pengirim)
    {
        $s = $this->ambil($jenis);
        $s->aktif = false;
        $s->save();
        Audit::catat('sambungan.matikan', $jenis);

        return $this->selesai($jenis, 'Sambungan '.Sambungan::definisi($jenis)['nama'].' dimatikan.', $pengirim);
    }

    public function gantiKunci(string $jenis, WebhookPengirim $pengirim)
    {
        $s = $this->ambil($jenis);
        abort_unless(Sambungan::definisi($jenis)['kunci'] && $s->exists, 404);
        $s->buatKunci();
        $s->save();
        Audit::catat('sambungan.ganti_kunci', $jenis);

        return $this->selesai($jenis, 'Kunci baru dibuat.', $pengirim);
    }

    public function kabari(string $jenis, WebhookPengirim $pengirim)
    {
        $this->ambil($jenis);

        return $this->selesai($jenis, 'Kabar dikirim ulang.', $pengirim);
    }
}
