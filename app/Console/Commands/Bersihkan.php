<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\WebhookOutbox;
use App\Services\SsoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class Bersihkan extends Command
{
    protected $signature = 'pusat:bersihkan';

    protected $description = 'Hapus kode/token SSO kedaluwarsa, file impor sementara, antrean webhook lama, dan audit > 1 tahun';

    public function handle(SsoService $sso): int
    {
        $h = $sso->bersihkan();

        $berkas = 0;
        foreach (Storage::disk('local')->files('impor') as $f) {
            if (Storage::disk('local')->lastModified($f) < now()->subDay()->getTimestamp()) {
                Storage::disk('local')->delete($f);
                $berkas++;
            }
        }

        $outbox = WebhookOutbox::where('status', 'terkirim')->where('terkirim_pada', '<', now()->subDays(30))->delete();
        $audit = AuditLog::where('created_at', '<', now()->subYear())->delete();

        $this->info("Kode: {$h['kode']}, token: {$h['token']}, file impor: {$berkas}, outbox: {$outbox}, audit: {$audit}");

        return self::SUCCESS;
    }
}
