<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use App\Models\Concerns\Tersinkron;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelas extends Model
{
    use PunyaUuid, SoftDeletes, Tersinkron;

    public const SUMBER = 'kelas';

    protected $table = 'kelas';

    protected $fillable = ['nama', 'tingkat', 'jurusan_id', 'tahun_ajaran_id', 'wali_id'];

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function wali(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'wali_id');
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'kelas_id');
    }
}
