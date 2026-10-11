<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use App\Models\Concerns\Tersinkron;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends Model
{
    use PunyaUuid, SoftDeletes, Tersinkron;

    public const SUMBER = 'siswa';

    protected $table = 'siswa';

    protected $fillable = [
        'nis', 'nisn', 'nama', 'jenis_kelamin', 'kelas_id', 'status',
        'tempat_lahir', 'tanggal_lahir', 'no_hp', 'no_hp_ortu', 'nama_ortu',
    ];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date:Y-m-d'];
    }

    protected static function booted(): void
    {
        // Siswa dihapus / lulus / pindah / keluar -> akun login terhubung dinonaktifkan.
        static::deleted(function (self $s) {
            User::where('siswa_id', $s->id)->get()->each->update(['aktif' => false]);
        });
        static::updated(function (self $s) {
            if ($s->wasChanged('status') && $s->status !== 'aktif') {
                User::where('siswa_id', $s->id)->get()->each->update(['aktif' => false]);
            }
        });
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function akun(): HasOne
    {
        return $this->hasOne(User::class, 'siswa_id');
    }
}
