<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use App\Models\Concerns\Tersinkron;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahunAjaran extends Model
{
    use PunyaUuid, SoftDeletes, Tersinkron;

    public const SUMBER = 'tahun_ajaran';

    protected $table = 'tahun_ajaran';

    protected $fillable = ['nama', 'mulai', 'selesai', 'aktif'];

    protected function casts(): array
    {
        return ['mulai' => 'date:Y-m-d', 'selesai' => 'date:Y-m-d', 'aktif' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Hanya satu tahun ajaran yang boleh aktif.
        static::saved(function (self $t) {
            if ($t->aktif) {
                static::where('id', '!=', $t->id)->where('aktif', true)->get()->each->update(['aktif' => false]);
            }
        });
    }

    public static function aktif(): ?self
    {
        return static::where('aktif', true)->latest('id')->first();
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'tahun_ajaran_id');
    }
}
