<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use App\Models\Concerns\Tersinkron;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pegawai extends Model
{
    use PunyaUuid, SoftDeletes, Tersinkron;

    public const SUMBER = 'pegawai';

    protected $table = 'pegawai';

    protected $fillable = ['nip', 'nama', 'jenis', 'jabatan', 'no_hp', 'email', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Pegawai dihapus -> akun yang terhubung dinonaktifkan (token ikut dicabut oleh User).
        static::deleted(function (self $p) {
            User::where('pegawai_id', $p->id)->get()->each->update(['aktif' => false]);
        });
        static::updated(function (self $p) {
            if ($p->wasChanged('aktif') && ! $p->aktif) {
                User::where('pegawai_id', $p->id)->get()->each->update(['aktif' => false]);
            }
        });
    }

    public function akun(): HasOne
    {
        return $this->hasOne(User::class, 'pegawai_id');
    }
}
