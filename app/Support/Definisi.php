<?php

namespace App\Support;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunAjaran;

/**
 * Definisi deklaratif data induk untuk panel admin: satu controller + dua view melayani
 * kelima sumber (tahun ajaran, jurusan, pegawai, kelas, siswa).
 *
 * field: [nama, label, tipe(text|date|select|checkbox), aturan, opsi(callable)?]
 */
final class Definisi
{
    public const SUMBER = ['tahun-ajaran', 'jurusan', 'pegawai', 'kelas', 'siswa'];

    public static function ambil(string $sumber): array
    {
        $semua = self::semua();
        abort_unless(isset($semua[$sumber]), 404);

        return $semua[$sumber];
    }

    public static function semua(): array
    {
        return [
            'tahun-ajaran' => [
                'model' => TahunAjaran::class,
                'tabel' => 'tahun_ajaran',
                'judul' => 'Tahun Ajaran',
                'kunci' => 'nama',
                'cari' => ['nama'],
                'urut' => ['nama', 'desc'],
                'kolom' => [
                    ['Nama', 'nama'],
                    ['Mulai', fn ($m) => $m->mulai?->format('d/m/Y')],
                    ['Selesai', fn ($m) => $m->selesai?->format('d/m/Y')],
                    ['Aktif', fn ($m) => $m->aktif ? 'Ya' : '—'],
                ],
                'field' => [
                    ['nama', 'Nama (mis. 2026/2027)', 'text', 'required|string|max:20'],
                    ['mulai', 'Mulai', 'date', 'nullable|date'],
                    ['selesai', 'Selesai', 'date', 'nullable|date|after_or_equal:mulai'],
                    ['aktif', 'Tahun ajaran aktif (hanya satu yang bisa aktif)', 'checkbox', 'boolean'],
                ],
                'penghalang' => fn ($m) => $m->kelas()->exists() ? 'Masih ada kelas pada tahun ajaran ini.' : null,
            ],
            'jurusan' => [
                'model' => Jurusan::class,
                'tabel' => 'jurusan',
                'judul' => 'Jurusan',
                'kunci' => 'kode',
                'cari' => ['kode', 'nama'],
                'urut' => ['kode', 'asc'],
                'kolom' => [
                    ['Kode', 'kode'],
                    ['Nama', 'nama'],
                    ['Aktif', fn ($m) => $m->aktif ? 'Ya' : '—'],
                ],
                'field' => [
                    ['kode', 'Kode (mis. DKV)', 'text', 'required|string|max:20'],
                    ['nama', 'Nama jurusan', 'text', 'required|string|max:255'],
                    ['aktif', 'Aktif', 'checkbox', 'boolean'],
                ],
                'penghalang' => fn ($m) => $m->kelas()->exists() ? 'Masih ada kelas yang memakai jurusan ini.' : null,
            ],
            'pegawai' => [
                'model' => Pegawai::class,
                'tabel' => 'pegawai',
                'judul' => 'Guru & Staf',
                'kunci' => 'nip',
                'cari' => ['nip', 'nama', 'jabatan'],
                'urut' => ['nama', 'asc'],
                'kolom' => [
                    ['NIP/ID', 'nip'],
                    ['Nama', 'nama'],
                    ['Jenis', 'jenis'],
                    ['Jabatan', 'jabatan'],
                    ['Aktif', fn ($m) => $m->aktif ? 'Ya' : '—'],
                ],
                'field' => [
                    ['nip', 'NIP / NUPTK / ID pegawai', 'text', 'required|string|max:30|regex:/^[A-Za-z0-9\/.\-]+$/'],
                    ['nama', 'Nama lengkap', 'text', 'required|string|max:150'],
                    ['jenis', 'Jenis', 'select', 'required|in:guru,staf', fn () => ['guru' => 'Guru', 'staf' => 'Staf']],
                    ['jabatan', 'Jabatan', 'text', 'nullable|string|max:100'],
                    ['no_hp', 'No. HP', 'text', 'nullable|string|max:30'],
                    ['email', 'Email', 'text', 'nullable|email|max:255'],
                    ['aktif', 'Aktif', 'checkbox', 'boolean'],
                ],
                'penghalang' => fn ($m) => Kelas::where('wali_id', $m->id)->exists() ? 'Masih menjadi wali kelas. Ganti wali kelasnya dulu.' : null,
            ],
            'kelas' => [
                'model' => Kelas::class,
                'tabel' => 'kelas',
                'judul' => 'Kelas',
                'unik' => ['nama', 'tahun_ajaran_id'],
                'with' => ['jurusan', 'tahunAjaran', 'wali'],
                'cari' => ['nama'],
                'urut' => ['nama', 'asc'],
                'kolom' => [
                    ['Kelas', 'nama'],
                    ['Tingkat', 'tingkat'],
                    ['Jurusan', fn ($m) => $m->jurusan?->kode],
                    ['Tahun ajaran', fn ($m) => $m->tahunAjaran?->nama],
                    ['Wali kelas', fn ($m) => $m->wali?->nama],
                ],
                'field' => [
                    ['nama', 'Nama kelas (mis. XI DKV 1)', 'text', 'required|string|max:50'],
                    ['tingkat', 'Tingkat', 'select', 'required|in:X,XI,XII', fn () => ['X' => 'X', 'XI' => 'XI', 'XII' => 'XII']],
                    ['jurusan_id', 'Jurusan', 'select', 'nullable|integer|exists:jurusan,id',
                        fn () => Jurusan::orderBy('kode')->pluck('nama', 'id')->all()],
                    ['tahun_ajaran_id', 'Tahun ajaran', 'select', 'required|integer|exists:tahun_ajaran,id',
                        fn () => TahunAjaran::orderByDesc('nama')->pluck('nama', 'id')->all()],
                    ['wali_id', 'Wali kelas', 'select', 'nullable|integer|exists:pegawai,id',
                        fn () => Pegawai::where('jenis', 'guru')->orderBy('nama')->pluck('nama', 'id')->all()],
                ],
                'penghalang' => fn ($m) => $m->siswa()->exists() ? 'Masih ada siswa di kelas ini. Pindahkan dulu.' : null,
            ],
            'siswa' => [
                'model' => Siswa::class,
                'tabel' => 'siswa',
                'judul' => 'Siswa',
                'kunci' => 'nis',
                'with' => ['kelas'],
                'cari' => ['nis', 'nisn', 'nama'],
                'urut' => ['nama', 'asc'],
                'kolom' => [
                    ['NIS', 'nis'],
                    ['Nama', 'nama'],
                    ['L/P', 'jenis_kelamin'],
                    ['Kelas', fn ($m) => $m->kelas?->nama],
                    ['Status', 'status'],
                ],
                'field' => [
                    ['nis', 'NIS', 'text', 'required|string|max:30|regex:/^[A-Za-z0-9\/.\-]+$/'],
                    ['nisn', 'NISN', 'text', 'nullable|digits_between:1,20'],
                    ['nama', 'Nama lengkap', 'text', 'required|string|max:150'],
                    ['jenis_kelamin', 'Jenis kelamin', 'select', 'nullable|in:L,P', fn () => ['L' => 'Laki-laki', 'P' => 'Perempuan']],
                    ['kelas_id', 'Kelas', 'select', 'nullable|integer|exists:kelas,id',
                        fn () => Kelas::with('tahunAjaran')->orderByDesc('tahun_ajaran_id')->orderBy('nama')->get()
                            ->mapWithKeys(fn ($k) => [$k->id => $k->nama.' ('.$k->tahunAjaran?->nama.')'])->all()],
                    ['status', 'Status', 'select', 'required|in:aktif,lulus,pindah,keluar',
                        fn () => ['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah', 'keluar' => 'Keluar']],
                    ['tempat_lahir', 'Tempat lahir', 'text', 'nullable|string|max:100'],
                    ['tanggal_lahir', 'Tanggal lahir', 'date', 'nullable|date|before:today'],
                    ['no_hp', 'No. HP siswa', 'text', 'nullable|string|max:30'],
                    ['no_hp_ortu', 'No. HP orang tua/wali', 'text', 'nullable|string|max:30'],
                    ['nama_ortu', 'Nama orang tua/wali', 'text', 'nullable|string|max:150'],
                ],
                'penghalang' => fn ($m) => null,
            ],
        ];
    }
}
