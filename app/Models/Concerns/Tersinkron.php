<?php

namespace App\Models\Concerns;

use App\Services\Audit;
use App\Services\Outbox;

/**
 * Setiap perubahan data induk otomatis: (1) dicatat di audit, (2) diantrekan sebagai
 * webhook ke aplikasi yang berhak. Model wajib punya const SUMBER (mis. 'siswa').
 */
trait Tersinkron
{
    public static function bootTersinkron(): void
    {
        static::created(function ($m) {
            Outbox::dari($m, 'dibuat');
            Audit::catat($m::SUMBER.'.dibuat', $m->uuid);
        });

        static::updated(function ($m) {
            // restore() juga memicu "updated"; kolom deleted_at ditangani event "restored".
            $kolom = array_values(array_diff(array_keys($m->getChanges()), ['updated_at', 'deleted_at']));
            if ($kolom === []) {
                return;
            }
            Outbox::dari($m, 'diperbarui');
            Audit::catat($m::SUMBER.'.diperbarui', $m->uuid, ['kolom' => $kolom]);
        });

        static::deleted(function ($m) {
            Outbox::dari($m, 'dihapus');
            Audit::catat($m::SUMBER.'.dihapus', $m->uuid);
        });

        static::restored(function ($m) {
            Outbox::dari($m, 'diperbarui');
            Audit::catat($m::SUMBER.'.dipulihkan', $m->uuid);
        });
    }
}
