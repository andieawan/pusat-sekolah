<?php

namespace App\Console\Commands;

use App\Services\WebhookPengirim;
use Illuminate\Console\Command;

class KirimWebhook extends Command
{
    protected $signature = 'webhook:kirim {--batas= : Maksimal entri per jalan}';

    protected $description = 'Kirim webhook yang jatuh tempo (dijalankan tiap menit oleh scheduler)';

    public function handle(WebhookPengirim $pengirim): int
    {
        $h = $pengirim->kirimJatuhTempo($this->option('batas') ? (int) $this->option('batas') : null);
        if ($h['dikirim'] || $h['gagal']) {
            $this->info("Terkirim: {$h['dikirim']}, gagal/ditunda: {$h['gagal']}");
        }

        return self::SUCCESS;
    }
}
