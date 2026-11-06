# Menyambungkan aplikasi ke Pusat

Berlaku untuk Jurnal Guru, BK, TU, PKL, Tanggungan Siswa, Website. Contoh memakai Laravel; logika sama di framework lain.

## 0. Persiapan
Daftarkan aplikasi (`php artisan pusat:aplikasi-baru ...`), lalu isi `.env` aplikasi:
```
PUSAT_URL=https://pusat.smkibu.example
PUSAT_CLIENT_ID=app_xxx
PUSAT_CLIENT_SECRET=xxx
PUSAT_WEBHOOK_SECRET=xxx
```

## 1. Login lewat Pusat (`/sso/login` dan `/sso/callback`)
```php
// Mulai login
Route::get('/sso/login', function (Request $r) {
    $verifier = Str::random(64);
    $state = Str::random(40);
    session(['sso_state' => $state, 'sso_verifier' => $verifier]);
    $tantangan = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return redirect(config('services.pusat.url').'/sso/authorize?'.http_build_query([
        'client_id' => config('services.pusat.client_id'),
        'redirect_uri' => route('sso.callback'),
        'response_type' => 'code', 'state' => $state,
        'code_challenge' => $tantangan, 'code_challenge_method' => 'S256',
    ]));
});

// Callback
Route::get('/sso/callback', function (Request $r) {
    abort_unless(hash_equals(session()->pull('sso_state', ''), (string) $r->query('state')), 400);
    $res = Http::withBasicAuth(config('services.pusat.client_id'), config('services.pusat.client_secret'))
        ->asForm()->post(config('services.pusat.url').'/api/sso/token', [
            'grant_type' => 'authorization_code', 'code' => $r->query('code'),
            'redirect_uri' => route('sso.callback'), 'code_verifier' => session()->pull('sso_verifier'),
        ]);
    abort_unless($res->ok(), 403);
    $u = $res['user'];
    // Cocokkan akun lokal lewat $u['sub'] (uuid Pusat), BUKAN lewat username/nama.
    $lokal = User::updateOrCreate(['pusat_uuid' => $u['sub']], ['nama' => $u['nama'], 'username' => $u['username']]);
    // Peran lokal diambil dari $u['peran'] (mis. 'admin', 'guru', 'bk', 'tu').
    Auth::login($lokal);
    $r->session()->regenerate();
    return redirect('/');
});
```
Tombol "Keluar" di aplikasi: logout lokal, lalu arahkan ke `PUSAT_URL/sso/logout?client_id=...&post_logout_redirect_uri=...`.
Selama masa transisi, login lokal lama boleh tetap ada sebagai cadangan; setelah stabil, matikan.

## 2. Penerima webhook
```php
Route::post('/hooks/pusat', function (Request $r) {
    $badan = $r->getContent();
    $waktu = (int) $r->header('X-Pusat-Timestamp');
    $ok = abs(time() - $waktu) <= 300
        && hash_equals('sha256='.hash_hmac('sha256', $waktu.'.'.$badan, config('services.pusat.webhook_secret')),
                       (string) $r->header('X-Pusat-Signature'));
    abort_unless($ok, 401);

    $id = $r->header('X-Pusat-Delivery');
    if (WebhookDiterima::where('delivery_id', $id)->exists()) return response('ok'); // idempoten
    WebhookDiterima::create(['delivery_id' => $id]);

    $e = $r->json('event'); $d = $r->json('data');
    // siswa.dibuat / siswa.diperbarui -> upsert berdasarkan $d['uuid']; siswa.dihapus -> hapus/arsipkan lokal
    return response('ok');
})->withoutMiddleware([VerifyCsrfToken::class]);
```
Kecualikan rute ini dari CSRF. Proses berat → masukkan ke queue, balas 2xx cepat (batas waktu Pusat 10 detik).

## 3. Sinkron data induk (jaring pengaman)
```php
// php artisan pusat:sinkron  (jadwalkan tiap jam)
$sejak = Cache::get('pusat_sejak'); $hal = 1;
do {
    $res = Http::withBasicAuth($id, $secret)->get("$url/api/v1/master/siswa", array_filter([
        'updated_since' => $sejak, 'per_page' => 500, 'page' => $hal,
    ]))->throw()->json();
    foreach ($res['data'] as $s) {
        $s['dihapus'] ? Siswa::where('pusat_uuid', $s['uuid'])->delete()
                      : Siswa::updateOrCreate(['pusat_uuid' => $s['uuid']], [...]);
    }
    $waktu = $res['meta']['waktu_server'];
} while ($hal++ < $res['meta']['halaman_terakhir']);
Cache::forever('pusat_sejak', Carbon::parse($waktu)->subSeconds(5)->toIso8601String());
```
Jalankan pertama kali tanpa `updated_since` (impor penuh). Tambahkan kolom `pusat_uuid` (unique) di tabel lokal.
Jangan menulis balik data induk ke Pusat dari aplikasi lain — ubah hanya di Pusat.

## 4. Contoh klien yang sudah ada
Klien paling ringkas ada di repo **tanggungan-pusat** (dan **pkl-pusat**, susunannya sama):
`app/Services/PusatKlien.php` (token & HTTP), `PusatSync.php` (sinkron penuh, tidak pernah menghapus),
`PusatAkun.php` (login SSO → akun lokal), `app/Http/Controllers/PusatSsoController.php` dan `PusatWebhookController.php`.
Salin dari sana untuk aplikasi baru, lalu sesuaikan `config/pusat.php` (`peran_peta`).

## 5. Aplikasi yang sudah tersambung
| Aplikasi | Templat di `config/pusat.php` | Catatan |
|---|---|---|
| Jurnal Guru | `jurnal` | pemberi data kehadiran untuk BK (sambungan "Jurnal Guru → BK") |
| BK | `bk` | membaca kehadiran dari Jurnal dan rekap PKL |
| TU | `tu` | menulis mutasi siswa ke Pusat (`siswa_tulis`) |
| PKL | `pkl` | mengambil nomor surat dari TU |
| Tanggungan Siswa | `tanggungan` | data siswa dasar saja |
| Website | `web` | ringkasan publik & jurusan |
