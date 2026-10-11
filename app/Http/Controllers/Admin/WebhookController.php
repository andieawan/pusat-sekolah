<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use App\Models\WebhookOutbox;
use App\Services\Audit;
use App\Services\WebhookPengirim;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function index(Request $request)
    {
        $q = WebhookOutbox::with('aplikasi')->orderByDesc('id');
        if (in_array($status = $request->query('status'), ['pending', 'terkirim', 'gagal'], true)) {
            $q->where('status', $status);
        }
        if ($aplikasi = (int) $request->query('aplikasi')) {
            $q->where('aplikasi_id', $aplikasi);
        }

        return view('admin.webhook.index', [
            'items' => $q->simplePaginate(30)->withQueryString(),
            'aplikasi' => Aplikasi::orderBy('nama')->pluck('nama', 'id'),
            'hitung' => WebhookOutbox::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function show(WebhookOutbox $webhook)
    {
        return view('admin.webhook.show', ['o' => $webhook->load('aplikasi')]);
    }

    /** Jadwalkan ulang satu entri yang gagal/tertunda dan kirim sekarang. */
    public function ulang(WebhookOutbox $webhook, WebhookPengirim $pengirim)
    {
        $webhook->update(['status' => 'pending', 'percobaan' => 0, 'jadwal_berikutnya' => now()]);
        $ok = $pengirim->kirim($webhook->load('aplikasi'));
        Audit::catat('webhook.ulang', $webhook->uuid);

        return back()->with($ok ? 'sukses' : 'galat', $ok ? 'Terkirim.' : 'Masih gagal: '.$webhook->fresh()->galat_terakhir);
    }

    public function ulangSemuaGagal()
    {
        $n = WebhookOutbox::where('status', 'gagal')->update([
            'status' => 'pending', 'percobaan' => 0, 'jadwal_berikutnya' => now(),
        ]);
        Audit::catat('webhook.ulang_semua', null, ['jumlah' => $n]);

        return back()->with('sukses', "{$n} entri dijadwalkan ulang (dikirim dalam satu menit).");
    }

    public function kirimSekarang(WebhookPengirim $pengirim)
    {
        $h = $pengirim->kirimJatuhTempo();

        return back()->with('sukses', "Diproses: {$h['dikirim']} terkirim, {$h['gagal']} gagal.");
    }
}
