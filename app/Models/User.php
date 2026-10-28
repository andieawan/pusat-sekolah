<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use App\Services\Outbox;
use App\Services\SsoService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use PunyaUuid;

    protected $fillable = [
        'username', 'nama', 'email', 'password', 'superadmin', 'operator_tu', 'aktif', 'pegawai_id', 'siswa_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'superadmin' => 'boolean',
            'operator_tu' => 'boolean',
            'aktif' => 'boolean',
            'login_terakhir' => 'datetime',
            'wajib_ganti_password' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $u) {
            if ($u->wasChanged('aktif') && ! $u->aktif) {
                app(SsoService::class)->cabutUser($u); // akun nonaktif: semua token mati
            }
            // password/wajib ganti/username ikut dikabarkan: aplikasi menyimpan salinan hash untuk login cadangan (scope akun_sandi)
            if ($u->wasChanged(['aktif', 'nama', 'email', 'superadmin', 'username', 'password', 'wajib_ganti_password'])) {
                // Status superadmin baru dicabut: kabari SEMUA aplikasi (peran admin implisit hilang di sana).
                Outbox::akun($u, $u->wasChanged('superadmin') && (bool) $u->getOriginal('superadmin'));
            }
        });
    }

    public function setUsernameAttribute(string $v): void
    {
        $this->attributes['username'] = strtolower(trim($v));
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function akses(): HasMany
    {
        return $this->hasMany(Akses::class, 'user_id');
    }

    /** Peran di sebuah aplikasi. Superadmin otomatis memegang peran "admin" di semua aplikasi. */
    public function peranUntuk(Aplikasi $app): array
    {
        $peran = $this->akses()->where('aplikasi_id', $app->id)->pluck('peran')->all();
        if ($this->superadmin) {
            $peran[] = 'admin';
        }

        return array_values(array_unique($peran));
    }

    public function bolehAkses(Aplikasi $app): bool
    {
        return $this->aktif && $this->peranUntuk($app) !== [];
    }

    public function tipe(): string
    {
        return $this->siswa_id ? 'siswa' : ($this->pegawai_id ? 'pegawai' : 'akun');
    }

    /** Boleh membuka panel Administrasi Pusat (superadmin, atau Operator TU untuk sebagian halaman). */
    public function bisaAdmin(): bool
    {
        return $this->superadmin || $this->operator_tu;
    }
}
