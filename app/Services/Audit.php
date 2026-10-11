<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class Audit
{
    /** Catat satu kejadian. Tidak pernah menggagalkan proses utama. Jangan isi detail dengan rahasia. */
    public static function catat(string $aksi, ?string $objek = null, array $detail = []): void
    {
        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'aksi' => $aksi,
                'objek' => $objek !== null ? mb_substr($objek, 0, 80) : null,
                'detail' => $detail ?: null,
                'ip' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
