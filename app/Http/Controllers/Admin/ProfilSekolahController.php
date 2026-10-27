<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use App\Services\Audit;
use App\Services\ProfilSekolah;
use App\Support\AturanPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Administrasi → Profil Sekolah: identitas & kop surat untuk semua aplikasi, logo, aturan password akun Pusat. */
class ProfilSekolahController extends Controller
{
    public function edit()
    {
        return view('admin.profil.edit', ['kolom' => ProfilSekolah::KOLOM, 'nilai' => Pengaturan::semua(),
            'logo' => ProfilSekolah::logoPath(), 'pw' => AturanPassword::nilai(), 'guruBk' => ProfilSekolah::guruBk()]);
    }

    public function update(Request $request)
    {
        $aturan = array_map(fn ($k) => $k[1], ProfilSekolah::KOLOM) + [
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048', 'hapus_logo' => 'nullable|boolean',
            'pw_min' => 'required|integer|min:8|max:64',
        ];
        $d = $request->validate($aturan);
        foreach (array_keys(ProfilSekolah::KOLOM) as $k) {
            Pengaturan::simpan($k, isset($d[$k]) && trim((string) $d[$k]) !== '' ? trim((string) $d[$k]) : null);
        }
        Pengaturan::simpan('pw_min', (string) $d['pw_min']);
        foreach (['pw_huruf', 'pw_angka', 'pw_simbol'] as $k) {
            Pengaturan::simpan($k, $request->boolean($k) ? '1' : '0');
        }
        if ($request->hasFile('logo')) {
            $lama = Pengaturan::ambil('logo');
            $path = $request->file('logo')->store('profil', 'local');
            Pengaturan::simpan('logo', $path);
            Pengaturan::simpan('logo_versi', (string) time());
            $lama && $lama !== $path && Storage::disk('local')->delete($lama);
        } elseif ($request->boolean('hapus_logo') && ($lama = Pengaturan::ambil('logo'))) {
            Storage::disk('local')->delete($lama);
            Pengaturan::simpan('logo', null);
            Pengaturan::simpan('logo_versi', null);
        }
        Audit::catat('profil.diperbarui');
        $n = ProfilSekolah::kabari();

        return back()->with('sukses', "Profil sekolah disimpan dan dikabarkan ke $n aplikasi (kop surat & kepala sekolah ikut berubah).");
    }

    /** Logo publik (dipakai kop surat & website). */
    public function logo()
    {
        $p = ProfilSekolah::logoPath();
        abort_unless($p, 404);

        return Storage::disk('local')->response($p, null, ['Cache-Control' => 'public, max-age=3600']);
    }
}
