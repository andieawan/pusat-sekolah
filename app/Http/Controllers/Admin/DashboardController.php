<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use App\Models\Kelas;
use App\Models\Pegawai;
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

        return view('admin.dashboard', [
            'tahun' => TahunAjaran::aktif(),
            'hitung' => [
                'Siswa aktif' => Siswa::where('status', 'aktif')->count(),
                'Guru & staf' => Pegawai::where('aktif', true)->count(),
                'Kelas' => Kelas::count(),
                'Akun aktif' => User::where('aktif', true)->count(),
            ],
            'tanpaAkses' => User::where('aktif', true)->where('superadmin', false)->doesntHave('akses')->count(),
            'aplikasi' => $aplikasi,
        ]);
    }
}
