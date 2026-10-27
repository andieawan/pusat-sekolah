<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Aplikasi extends Model
{
    use PunyaUuid;

    protected $table = 'aplikasi';

    protected $fillable = [
        'kode', 'nama', 'base_url', 'redirect_uris', 'webhook_url', 'scope', 'peran_tersedia', 'aktif',
    ];

    protected $hidden = ['client_secret_hash', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'scope' => 'array',
            'peran_tersedia' => 'array',
            'aktif' => 'boolean',
            'webhook_secret' => 'encrypted',
            'secret_dibuat_pada' => 'datetime',
        ];
    }

    /** @return array{0:string,1:string} [client_id, client_secret] polos (tampilkan sekali saja). */
    public function buatKredensial(): array
    {
        $secret = Str::random(48);
        $this->client_id ??= 'app_'.Str::lower(Str::random(24));
        $this->client_secret_hash = hash('sha256', $secret);
        $this->secret_dibuat_pada = now();

        return [$this->client_id, $secret];
    }

    public function cocokSecret(string $secret): bool
    {
        return $secret !== '' && hash_equals((string) $this->client_secret_hash, hash('sha256', $secret));
    }

    public function punyaScope(string $scope): bool
    {
        return in_array($scope, $this->scope ?? [], true);
    }

    /**
     * Tingkat akses ke satu sumber data: 'lengkap', 'dasar', atau null (tidak berhak).
     * Untuk siswa/pegawai ada dua tingkat; sumber lain hanya ada/tidak ada.
     */
    public function aksesSumber(string $sumber): ?string
    {
        if (in_array($sumber, ['siswa', 'pegawai'], true)) {
            if ($this->punyaScope($sumber)) {
                return 'lengkap';
            }

            return $this->punyaScope($sumber.'_dasar') ? 'dasar' : null;
        }

        return $this->punyaScope($sumber) ? 'lengkap' : null;
    }

    /**
     * Alamat untuk langsung masuk ke aplikasi lewat SSO (tanpa halaman login aplikasi):
     * redirect …/sso/callback → …/sso/login (Absensi, BK, TU, PKL); …/pusat/callback → …/pusat/masuk (Website).
     */
    public function urlMasuk(): string
    {
        $cb = (string) (($this->redirect_uris ?? [])[0] ?? '');
        foreach (['/sso/callback' => '/sso/login', '/pusat/callback' => '/pusat/masuk'] as $akhir => $mulai) {
            if (str_ends_with($cb, $akhir)) {
                return substr($cb, 0, -strlen($akhir)).$mulai;
            }
        }

        return (string) $this->base_url;
    }

    public function akses(): HasMany
    {
        return $this->hasMany(Akses::class, 'aplikasi_id');
    }

    public function outbox(): HasMany
    {
        return $this->hasMany(WebhookOutbox::class, 'aplikasi_id');
    }
}
