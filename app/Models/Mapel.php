<?php

namespace App\Models;

use App\Models\Concerns\PunyaUuid;
use App\Models\Concerns\Tersinkron;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mapel extends Model
{
    use PunyaUuid, SoftDeletes, Tersinkron;

    public const SUMBER = 'mapel';

    public const KELOMPOK = ['umum' => 'Umum', 'kejuruan' => 'Kejuruan', 'mulok' => 'Muatan Lokal'];

    protected $table = 'mapel';

    protected $fillable = ['kode', 'nama', 'kelompok', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function setKodeAttribute(string $v): void
    {
        $this->attributes['kode'] = strtoupper(trim($v));
    }
}
