# Kontrak API Pusat Sekolah

Semua respons JSON. Galat: `{"error":{"kode":"...","pesan":"..."}}`. Waktu memakai ISO 8601.

## Autentikasi

| Jalur | Dipakai untuk | Cara |
|---|---|---|
| Server-ke-server | `/api/v1/*`, `POST /api/sso/token` | HTTP Basic `client_id:client_secret` |
| Bearer | `/api/sso/userinfo`, `/api/sso/revoke` | `Authorization: Bearer <access_token>` |

Batas laju: `/api/sso/*` 60/menit per IP; `/api/v1/*` 120/menit per client_id (HTTP 429).

## SSO

1. Browser → `GET /sso/authorize?client_id&redirect_uri&response_type=code&state[&code_challenge&code_challenge_method=S256]`
   - `redirect_uri` harus persis salah satu yang terdaftar; jika salah → halaman galat 400 (tanpa redirect).
   - Belum login → halaman login Pusat, lalu kembali. Tanpa peran di aplikasi → 403.
   - Sukses → 302 ke `redirect_uri?code=...&state=...` (kode berlaku 60 detik, sekali pakai).
2. Server aplikasi → `POST /api/sso/token` (Basic) body: `grant_type=authorization_code`, `code`, `redirect_uri`, `[code_verifier]`
   → `{"access_token","token_type":"Bearer","expires_in":3600,"user":{...}}`. Galat 400 `kode_tidak_valid`, 401 `klien_tidak_sah`.
3. `GET /api/sso/userinfo` (Bearer) → profil. 403 `akses_dicabut` bila akun tak lagi berhak, 401 bila token mati.
4. `POST /api/sso/revoke` (Bearer) → `{"ok":true}`.
5. `GET /sso/logout?client_id&post_logout_redirect_uri` → keluar dari Pusat dan mencabut semua token akun.

Objek `user`: `sub` (uuid), `username`, `nama`, `email`, `tipe` (siswa|pegawai|akun), `peran[]`, `pegawai{uuid,nip,jenis,jabatan}|null`, `siswa{uuid,nis,kelas_uuid,kelas_nama}|null`.
Superadmin otomatis memiliki peran `admin` di semua aplikasi.

## Data induk

`GET /api/v1/master/{sumber}` — sumber: `tahun-ajaran`, `jurusan`, `kelas`, `siswa`, `pegawai`.

| Parameter | Arti |
|---|---|
| `updated_since` | hanya yang berubah setelah waktu ini, **termasuk yang dihapus** (field `dihapus` terisi) |
| `per_page` | 1–500, bawaan 200 |
| `page` | halaman |
| `status` | khusus siswa: aktif, lulus, pindah, keluar |

Scope aplikasi: `siswa` (lengkap) atau `siswa_dasar` (tanpa tgl lahir/kontak); sama untuk `pegawai`/`pegawai_dasar`;
`tahun_ajaran`, `jurusan`, `kelas`, `akun`, `ringkasan`. Tanpa scope → 403 `scope_tidak_cukup`.

Respons: `{"data":[...],"meta":{"halaman","per_halaman","total","halaman_terakhir","waktu_server"}}`.
Simpan `meta.waktu_server` (dikurangi ±5 detik) sebagai `updated_since` sinkron berikutnya.

## Lainnya

- `GET /api/v1/ping` → `{"ok":true,"waktu_server":"..."}`
- `GET /api/v1/ringkasan` (scope `ringkasan`) → agregat aman-publik (jumlah siswa per jurusan, dst.), cache 5 menit
- `GET /api/v1/akun` (scope `akun`) → akun beserta peran di aplikasi pemanggil

## Tulis siswa (scope `siswa_tulis`, untuk mutasi di Tata Usaha)

- `POST /api/v1/siswa` `{nis, nama, jenis_kelamin: L|P, kelas_uuid, nisn?, tempat_lahir?, tanggal_lahir? (Y-m-d), no_hp?, no_hp_ortu?, nama_ortu?, petugas?}`
  → `201 {"data": siswa}`. NIS yang sudah dipakai → `409 nis_sudah_ada`; NIS siswa yang pernah dihapus → dipulihkan (uuid sama).
- `PATCH /api/v1/siswa/{uuid}` `{status?: aktif|lulus|pindah|keluar, kelas_uuid?, petugas?}` → `200 {"data": siswa}`.
  Sengaja hanya status & kelas; nama/NIS/data pribadi diubah di panel Pusat.

Setiap perubahan tercatat di Audit (aplikasi + `petugas`) dan dikirim sebagai webhook `siswa.*` ke aplikasi lain.

## Webhook

`POST` ke `webhook_url` aplikasi, badan JSON `{"id","event","waktu","data":{...}}`.

Header: `X-Pusat-Event`, `X-Pusat-Delivery` (uuid, **sama** saat dicoba ulang → jadikan kunci idempotensi),
`X-Pusat-Timestamp` (detik UNIX), `X-Pusat-Signature` = `sha256=` + HMAC-SHA256(`webhook_secret`, `"{timestamp}.{badan mentah}"`).

Event: `siswa.dibuat|diperbarui|dihapus`, `pegawai.*`, `kelas.*`, `jurusan.*`, `tahun_ajaran.*`, `akses.diperbarui`, `akun.diperbarui`, `ping`.

Balas HTTP 2xx bila sudah diterima. Selain itu dicoba ulang: 1 mnt, 5 mnt, 15 mnt, 1 jam, 6 jam; setelah 6 percobaan berstatus `gagal`
(bisa dikirim ulang dari panel). Tolak bila timestamp selisih > 5 menit atau tanda tangan salah.
Webhook bisa terlambat atau hilang → sinkron berkala `updated_since` (mis. tiap jam) tetap wajib sebagai jaring pengaman.
