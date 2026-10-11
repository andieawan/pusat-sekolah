<?php

namespace App\Services;

use App\Models\Aplikasi;
use App\Models\SsoKode;
use App\Models\SsoToken;
use App\Models\User;
use App\Support\Pkce;
use Illuminate\Support\Str;

class SsoService
{
    private static function hash(string $v): string
    {
        return hash('sha256', $v);
    }

    /** Kode otorisasi sekali pakai, berlaku singkat. Hanya hash-nya yang disimpan. */
    public function buatKode(User $user, Aplikasi $app, string $redirectUri, ?string $tantangan): string
    {
        $kode = Str::random(64);
        SsoKode::create([
            'kode_hash' => self::hash($kode),
            'user_id' => $user->id,
            'aplikasi_id' => $app->id,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $tantangan,
            'kedaluwarsa_pada' => now()->addSeconds((int) config('pusat.kode_ttl')),
            'created_at' => now(),
        ]);

        return $kode;
    }

    /**
     * Tukar kode dengan token akses. Mengembalikan null jika kode salah/kedaluwarsa/sudah dipakai,
     * redirect_uri berbeda, PKCE tidak cocok, atau akun tidak (lagi) berhak.
     *
     * @return array{access_token:string, token_type:string, expires_in:int, user:array}|null
     */
    public function tukarKode(Aplikasi $app, string $kode, string $redirectUri, ?string $verifier): ?array
    {
        $rec = SsoKode::where('kode_hash', self::hash($kode))->where('aplikasi_id', $app->id)->first();
        if (! $rec || $rec->digunakan_pada || $rec->kedaluwarsa_pada->isPast()) {
            return null;
        }

        // Tandai terpakai secara atomik: hanya satu penukar yang menang (anti replay/race).
        $klaim = SsoKode::where('id', $rec->id)->whereNull('digunakan_pada')->update(['digunakan_pada' => now()]);
        if ($klaim !== 1) {
            return null;
        }

        if (! hash_equals($rec->redirect_uri, $redirectUri)) {
            return null;
        }
        if ($rec->code_challenge !== null && ($verifier === null || ! Pkce::cocok($verifier, $rec->code_challenge))) {
            return null;
        }

        $user = User::find($rec->user_id);
        if (! $user || ! $user->bolehAkses($app)) {
            return null;
        }

        $token = Str::random(64);
        $ttl = (int) config('pusat.token_ttl');
        SsoToken::create([
            'token_hash' => self::hash($token),
            'user_id' => $user->id,
            'aplikasi_id' => $app->id,
            'kedaluwarsa_pada' => now()->addSeconds($ttl),
            'created_at' => now(),
        ]);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => $this->userinfo($user, $app),
        ];
    }

    public function tokenValid(string $token): ?SsoToken
    {
        $t = SsoToken::with(['user', 'aplikasi'])->where('token_hash', self::hash($token))->first();
        if (! $t || $t->dicabut_pada || $t->kedaluwarsa_pada->isPast()) {
            return null;
        }

        return $t;
    }

    public function cabutToken(SsoToken $t): void
    {
        $t->update(['dicabut_pada' => now()]);
    }

    /** Cabut semua token akun (logout tunggal, akun dinonaktifkan, password diganti). */
    public function cabutUser(User $u, ?int $aplikasiId = null): void
    {
        SsoToken::where('user_id', $u->id)
            ->when($aplikasiId, fn ($q) => $q->where('aplikasi_id', $aplikasiId))
            ->whereNull('dicabut_pada')
            ->update(['dicabut_pada' => now()]);
    }

    /** Profil + peran akun untuk satu aplikasi. */
    public function userinfo(User $u, Aplikasi $app): array
    {
        $u->loadMissing(['pegawai', 'siswa.kelas']);

        return [
            'sub' => $u->uuid,
            'username' => $u->username,
            'nama' => $u->nama,
            'email' => $u->email,
            'tipe' => $u->tipe(),
            'peran' => $u->peranUntuk($app),
            'pegawai' => $u->pegawai ? [
                'uuid' => $u->pegawai->uuid,
                'nip' => $u->pegawai->nip,
                'jenis' => $u->pegawai->jenis,
                'jabatan' => $u->pegawai->jabatan,
            ] : null,
            'siswa' => $u->siswa ? [
                'uuid' => $u->siswa->uuid,
                'nis' => $u->siswa->nis,
                'kelas_uuid' => $u->siswa->kelas?->uuid,
                'kelas_nama' => $u->siswa->kelas?->nama,
            ] : null,
        ];
    }

    /** Hapus kode/token kedaluwarsa (dipanggil terjadwal). */
    public function bersihkan(): array
    {
        $k = SsoKode::where('kedaluwarsa_pada', '<', now()->subDay())->delete();
        $t = SsoToken::where('kedaluwarsa_pada', '<', now()->subDays(7))->delete();

        return ['kode' => $k, 'token' => $t];
    }
}
