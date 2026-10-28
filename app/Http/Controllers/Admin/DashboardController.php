<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\Sambungan;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\WebhookOutbox;

class DashboardController extends Controller
{
    public function index()
    {
        $aplikasi = Aplikasi::withCount('akses')->orderBy('nama')->get()->map(function ($a) {
            $a->outbox_pending = WebhookOutbox::where('aplikasi_id', $a->id)->where('status', 'pending')->count();
            $a->outbox_gagal = WebhookOutbox::where('aplikasi_id', $a->id)->where('status', 'gagal')->count();
            $a->terakhir_kirim = WebhookOutbox::where('aplikasi_id', $a->id)->where('status', 'terkirim')->max('terkirim_pada');

            return $a;
        });
        $tahun = TahunAjaran::aktif();
        $super = (bool) auth()->user()->superadmin;
        $tugas = $this->tugas($tahun, $aplikasi);
        if (! $super) { // Operator TU: hanya tugas yang bisa ia kerjakan
            $boleh = [route('admin.profil.edit'), route('admin.master.index', 'kelas'), route('admin.master.index', 'siswa'), route('admin.akun.massal')];
            $tugas = array_values(array_filter($tugas, fn ($t) => in_array($t[3], $boleh, true)));
        }

        return view('admin.dashboard', [
            'tahun' => $tahun,
            'hitung' => [
                ['Siswa aktif', Siswa::where('status', 'aktif')->count(), route('admin.master.index', 'siswa')],
                ['Guru & staf', Pegawai::where('aktif', true)->count(), route('admin.master.index', 'pegawai')],
                ['Kelas '.($tahun?->nama ?? ''), $tahun ? Kelas::where('tahun_ajaran_id', $tahun->id)->count() : Kelas::count(), route('admin.master.index', 'kelas')],
                ['Akun aktif', User::where('aktif', true)->count(), $super ? route('admin.akun.index') : route('admin.akun.massal')],
            ],
            'tugas' => $tugas,
            'aplikasi' => $aplikasi,
        ]);
    }

    /** Daftar periksa: apa yang sudah beres dan apa yang masih perlu dikerjakan. @return array<int,array> */
    private function tugas(?TahunAjaran $tahun, $aplikasi): array
    {
        $t = [];
        $profil = Pengaturan::ambil('nama') !== null && Pengaturan::ambil('kepsek') !== null;
        $t[] = [$profil, 'Profil sekolah', $profil ? 'Kop surat & kepala sekolah dipakai semua aplikasi.' : 'Isi nama sekolah, kepala sekolah, dan logo agar kop surat semua aplikasi seragam.',
            route('admin.profil.edit'), 'Isi profil'];
        $t[] = [(bool) $tahun, 'Tahun ajaran aktif', $tahun ? "{$tahun->nama}, semester {$tahun->semester}." : 'Belum ada tahun ajaran aktif — dibutuhkan untuk kelas, impor, dan kenaikan kelas.',
            route('admin.master.index', 'tahun-ajaran'), 'Atur'];
        $tanpaWali = $tahun ? Kelas::where('tahun_ajaran_id', $tahun->id)->whereNull('wali_id')->count() : 0;
        $t[] = [$tanpaWali === 0, 'Wali kelas', $tanpaWali ? "$tanpaWali kelas belum punya wali kelas (wali kelas di Absensi & BK mengikuti isian ini)." : 'Semua kelas sudah punya wali kelas.',
            route('admin.master.index', 'kelas'), 'Atur wali'];
        $tanpaKelas = Siswa::where('status', 'aktif')->whereNull('kelas_id')->count();
        if ($tanpaKelas) {
            $t[] = [false, 'Siswa tanpa kelas', "$tanpaKelas siswa aktif belum masuk kelas.", route('admin.master.index', 'siswa'), 'Lihat siswa'];
        }
        $guruTanpaAkun = Pegawai::where('aktif', true)->whereDoesntHave('akun')->count();
        $t[] = [$guruTanpaAkun === 0, 'Akun guru & staf', $guruTanpaAkun ? "$guruTanpaAkun guru/staf belum punya akun login." : 'Semua guru & staf sudah punya akun.',
            route('admin.akun.massal'), 'Buat akun'];
        $tanpaAkses = User::where('aktif', true)->where('superadmin', false)->doesntHave('akses')->count();
        if ($tanpaAkses) {
            $t[] = [false, 'Akun tanpa akses', "$tanpaAkses akun aktif belum diberi peran di aplikasi mana pun.", route('admin.akun.index'), 'Lihat akun'];
        }
        $gagal = $aplikasi->sum('outbox_gagal');
        $t[] = [$gagal === 0, 'Pengiriman ke aplikasi', $gagal ? "$gagal kabar perubahan gagal terkirim (aplikasi mati atau alamat salah)." : 'Semua kabar perubahan terkirim.',
            route('admin.webhook.index', ['status' => 'gagal']), 'Periksa'];
        $nyala = Sambungan::where('aktif', true)->count();
        $total = count((array) config('pusat.sambungan'));
        $t[] = [$nyala === $total, 'Sambungan antar-aplikasi', "$nyala dari $total sambungan aktif.", route('admin.sambungan.index'), 'Atur'];

        usort($t, fn ($a, $b) => (int) $a[0] <=> (int) $b[0]); // yang belum beres di atas

        return $t;
    }
}
