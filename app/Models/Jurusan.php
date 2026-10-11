<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use App\Models\Concerns\Tersinkron;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Jurusan extends Model
{
    use PunyaUuid, SoftDeletes, Tersinkron;

    public const SUMBER = 'jurusan';

    protected $table = 'jurusan';

    protected $fillable = ['kode', 'nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function setKodeAttribute(string $v): void
    {
        $this->attributes['kode'] = strtoupper(trim($v));
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'jurusan_id');
    }
}
