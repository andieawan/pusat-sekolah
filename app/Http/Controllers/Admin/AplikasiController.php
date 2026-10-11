<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use App\Services\Audit;
use App\Services\Outbox;
use App\Services\WebhookPengirim;
use App\Support\RedirectUri;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AplikasiController extends Controller
{
    public function index()
    {
        return view('admin.aplikasi.index', [
            'items' => Aplikasi::withCount('akses')->orderBy('nama')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $app = new Aplikasi(['aktif' => true, 'scope' => [], 'peran_tersedia' => [], 'redirect_uris' => []]);
        $bawaan = config('pusat.aplikasi_bawaan.'.$request->query('kode'));
        if ($bawaan) {
            $app->kode = $request->query('kode');
            $app->nama = $bawaan['nama'];
            $app->scope = $bawaan['scope'];
            $app->peran_tersedia = $bawaan['peran'];
        }

        return view('admin.aplikasi.form', ['app' => $app, 'baru' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request, null);
        $app = new Aplikasi($data);
        [$clientId, $secret] = $app->buatKredensial();
        $webhookSecret = Str::random(48);
        $app->webhook_secret = $webhookSecret;
        $app->save();
        Audit::catat('aplikasi.dibuat', $app->kode);

        // Ditampilkan langsung (tanpa redirect/session) agar rahasia tidak tersimpan di mana pun selain hash-nya.
        return response()->view('admin.aplikasi.kredensial', [
            'app' => $app, 'clientId' => $clientId, 'secret' => $secret, 'webhookSecret' => $webhookSecret, 'judul' => 'Aplikasi terdaftar',
        ]);
    }

    public function edit(Aplikasi $aplikasi)
    {
        return view('admin.aplikasi.form', ['app' => $aplikasi, 'baru' => false]);
    }

    public function update(Request $request, Aplikasi $aplikasi)
    {
        $aplikasi->update($this->validasi($request, $aplikasi));
        Audit::catat('aplikasi.diperbarui', $aplikasi->kode);

        return redirect()->route('admin.aplikasi.index')->with('sukses', "Aplikasi {$aplikasi->nama} diperbarui.");
    }

    public function destroy(Aplikasi $aplikasi)
    {
        Audit::catat('aplikasi.dihapus', $aplikasi->kode);
        $aplikasi->delete();

        return redirect()->route('admin.aplikasi.index')->with('sukses', 'Aplikasi dihapus.');
    }

    /** Buat rahasia baru. Rahasia lama langsung tidak berlaku. */
    public function rotasi(Request $request, Aplikasi $aplikasi)
    {
        $jenis = $request->validate(['jenis' => ['required', 'in:klien,webhook']])['jenis'];
        $secret = null;
        $webhookSecret = null;

        if ($jenis === 'klien') {
            [, $secret] = $aplikasi->buatKredensial();
            $aplikasi->save();
        } else {
            $webhookSecret = Str::random(48);
            $aplikasi->webhook_secret = $webhookSecret;
            $aplikasi->save();
        }
        Audit::catat('aplikasi.rotasi_'.$jenis, $aplikasi->kode);

        return response()->view('admin.aplikasi.kredensial', [
            'app' => $aplikasi, 'clientId' => $aplikasi->client_id, 'secret' => $secret,
            'webhookSecret' => $webhookSecret, 'judul' => 'Rahasia baru dibuat',
        ]);
    }

    /** Kirim event "ping" dan tampilkan hasilnya langsung. */
    public function ping(Aplikasi $aplikasi, WebhookPengirim $pengirim)
    {
        if (! $aplikasi->webhook_url) {
            return back()->with('galat', 'URL webhook belum diisi.');
        }
        $o = Outbox::antre($aplikasi, 'ping', ['pesan' => 'Uji koneksi dari Pusat']);
        $ok = $pengirim->kirim($o);
        $o->refresh();

        return back()->with(
            $ok ? 'sukses' : 'galat',
            $ok ? "Webhook {$aplikasi->nama} merespons HTTP {$o->kode_http}." : "Webhook {$aplikasi->nama} gagal: {$o->galat_terakhir}. Akan dicoba ulang otomatis.",
        );
    }

    private function validasi(Request $request, ?Aplikasi $app): array
    {
        $request->merge(['kode' => $app?->kode ?? strtolower(trim((string) $request->input('kode')))]);

        $v = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/', Rule::unique('aplikasi', 'kode')->ignore($app?->id)],
            'nama' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'url:http,https', 'max:255'],
            'redirect_uris' => ['required', 'string', 'max:2000'],
            'webhook_url' => ['nullable', 'url:http,https', 'max:255'],
            'scope' => ['nullable', 'array'],
            'scope.*' => ['in:'.implode(',', array_keys(config('pusat.scope')))],
            'peran_tersedia' => ['required', 'string', 'max:1000'],
        ]);

        $uris = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $v['redirect_uris'])))));
        foreach ($uris as $u) {
            if (! RedirectUri::sahBentuk($u)) {
                throw ValidationException::withMessages(['redirect_uris' => "Redirect URI tidak sah: {$u} (harus http/https tanpa fragment)."]);
            }
        }
        $peran = array_values(array_unique(array_filter(array_map(
            fn ($p) => strtolower(trim($p)),
            preg_split('/[\s,]+/', $v['peran_tersedia']),
        ))));
        foreach ($peran as $p) {
            if (! preg_match('/^[a-z0-9_]{1,40}$/', $p)) {
                throw ValidationException::withMessages(['peran_tersedia' => "Nama peran tidak sah: {$p} (huruf kecil, angka, garis bawah)."]);
            }
        }

        return [
            'kode' => $v['kode'],
            'nama' => $v['nama'],
            'base_url' => rtrim($v['base_url'], '/'),
            'redirect_uris' => $uris,
            'webhook_url' => $v['webhook_url'] ?: null,
            'scope' => array_values($v['scope'] ?? []),
            'peran_tersedia' => $peran,
            'aktif' => $request->boolean('aktif'),
        ];
    }
}
