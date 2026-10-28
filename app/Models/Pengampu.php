<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use App\Models\Concerns\Tersinkron;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Guru (pegawai) mengampu satu mapel di satu kelas. */
class Pengampu extends Model
{
    use PunyaUuid, SoftDeletes, Tersinkron;

    public const SUMBER = 'pengampu';

    protected $table = 'pengampu';

    protected $fillable = ['pegawai_id', 'mapel_id', 'kelas_id'];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id')->withTrashed();
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapel_id')->withTrashed();
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id')->withTrashed();
    }
}
