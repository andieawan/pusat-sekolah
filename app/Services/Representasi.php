<?php

namespace App\Services;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Bentuk JSON data induk. Dipakai bersama oleh API (/api/v1/master) dan webhook,
 * sehingga aplikasi menerima struktur yang sama dari kedua jalur.
 */
final class Representasi
{
    public static function dari(Model $m, bool $lengkap = true): array
    {
        return match (true) {
            $m instanceof Siswa => self::siswa($m, $lengkap),
            $m instanceof Pegawai => self::pegawai($m, $lengkap),
            $m instanceof Kelas => self::kelas($m),
            $m instanceof Jurusan => self::jurusan($m),
            $m instanceof TahunAjaran => self::tahunAjaran($m),
            $m instanceof \App\Models\Mapel => self::mapel($m),
            $m instanceof \App\Models\Pengampu => self::pengampu($m),
            default => throw new InvalidArgumentException('Model tidak didukung: '.$m::class),
        };
    }

    private static function waktu($v): ?string
    {
        return $v?->toIso8601String();
    }

    public static function siswa(Siswa $s, bool $lengkap): array
    {
        $s->loadMissing('kelas');
        $dasar = [
            'uuid' => $s->uuid,
            'nis' => $s->nis,
            'nisn' => $s->nisn,
            'nama' => $s->nama,
            'jenis_kelamin' => $s->jenis_kelamin,
            'status' => $s->status,
            'kelas_uuid' => $s->kelas?->uuid,
            'kelas_nama' => $s->kelas?->nama,
        ];
        if ($lengkap) {
            $dasar += [
                'tempat_lahir' => $s->tempat_lahir,
                'tanggal_lahir' => $s->tanggal_lahir?->format('Y-m-d'),
                'no_hp' => $s->no_hp,
                'no_hp_ortu' => $s->no_hp_ortu,
                'nama_ortu' => $s->nama_ortu,
                'alamat' => $s->alamat,
            ];
        }

        return $dasar + ['diperbarui' => self::waktu($s->updated_at), 'dihapus' => self::waktu($s->deleted_at)];
    }

    public static function pegawai(Pegawai $p, bool $lengkap): array
    {
        $dasar = [
            'uuid' => $p->uuid,
            'nip' => $p->nip,
            'nama' => $p->nama,
            'jenis' => $p->jenis,
            'jabatan' => $p->jabatan,
            'aktif' => (bool) $p->aktif,
        ];
        if ($lengkap) {
            $dasar += ['no_hp' => $p->no_hp, 'email' => $p->email];
        }

        return $dasar + ['diperbarui' => self::waktu($p->updated_at), 'dihapus' => self::waktu($p->deleted_at)];
    }

    public static function kelas(Kelas $k): array
    {
        $k->loadMissing(['jurusan', 'tahunAjaran', 'wali']);

        return [
            'uuid' => $k->uuid,
            'nama' => $k->nama,
            'tingkat' => $k->tingkat,
            'jurusan_uuid' => $k->jurusan?->uuid,
            'jurusan_kode' => $k->jurusan?->kode,
            'tahun_ajaran_uuid' => $k->tahunAjaran?->uuid,
            'tahun_ajaran' => $k->tahunAjaran?->nama,
            'wali_uuid' => $k->wali?->uuid,
            'wali_nama' => $k->wali?->nama,
            'diperbarui' => self::waktu($k->updated_at),
            'dihapus' => self::waktu($k->deleted_at),
        ];
    }

    public static function jurusan(Jurusan $j): array
    {
        return [
            'uuid' => $j->uuid,
            'kode' => $j->kode,
            'nama' => $j->nama,
            'aktif' => (bool) $j->aktif,
            'diperbarui' => self::waktu($j->updated_at),
            'dihapus' => self::waktu($j->deleted_at),
        ];
    }

    public static function mapel(\App\Models\Mapel $m): array
    {
        return [
            'uuid' => $m->uuid,
            'kode' => $m->kode,
            'nama' => $m->nama,
            'kelompok' => $m->kelompok,
            'aktif' => (bool) $m->aktif,
            'diperbarui' => self::waktu($m->updated_at),
            'dihapus' => self::waktu($m->deleted_at),
        ];
    }

    public static function tahunAjaran(TahunAjaran $t): array
    {
        return [
            'uuid' => $t->uuid,
            'nama' => $t->nama,
            'mulai' => $t->mulai?->format('Y-m-d'),
            'selesai' => $t->selesai?->format('Y-m-d'),
            'semester' => $t->semester ?: 'Ganjil',
            'aktif' => (bool) $t->aktif,
            'diperbarui' => self::waktu($t->updated_at),
            'dihapus' => self::waktu($t->deleted_at),
        ];
    }

    /** Akun beserta peran di satu aplikasi. Tidak pernah memuat password. */
    /**
     * @param  bool  $sandi  sertakan hash password (bcrypt) untuk login cadangan di aplikasi — hanya untuk aplikasi
     *                       berizin "akun_sandi". Hash TIDAK dikirim selama akun masih wajib ganti password awal.
     */
    public static function akun(User $u, array $peran, bool $sandi = false): array
    {
        $u->loadMissing(['pegawai', 'siswa']);

        return ($sandi ? ['password_hash' => $u->wajib_ganti_password ? null : $u->getAuthPassword()] : []) + [
            'uuid' => $u->uuid,
            'username' => $u->username,
            'nama' => $u->nama,
            'email' => $u->email,
            'aktif' => (bool) $u->aktif,
            'tipe' => $u->tipe(),
            'pegawai_uuid' => $u->pegawai?->uuid,
            'siswa_uuid' => $u->siswa?->uuid,
            'peran' => array_values($peran),
            'diperbarui' => self::waktu($u->updated_at),
        ];
    }

    public static function pengampu(\App\Models\Pengampu $p): array
    {
        $p->loadMissing(['pegawai', 'mapel', 'kelas']);

        return [
            'uuid' => $p->uuid,
            'pegawai_uuid' => $p->pegawai?->uuid,
            'pegawai_nama' => $p->pegawai?->nama,
            'mapel_uuid' => $p->mapel?->uuid,
            'mapel_nama' => $p->mapel?->nama,
            'kelas_uuid' => $p->kelas?->uuid,
            'kelas_nama' => $p->kelas?->nama,
            'diperbarui' => self::waktu($p->updated_at),
            'dihapus' => self::waktu($p->deleted_at),
        ];
    }
}
