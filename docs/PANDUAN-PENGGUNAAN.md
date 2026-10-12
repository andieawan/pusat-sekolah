# Panduan penggunaan: Pusat Sekolah dan aplikasi di bawahnya

## Gambaran singkat
- **Pusat** adalah sumber tunggal data induk (tahun ajaran, jurusan, kelas, siswa, guru/staf) dan pengatur akun + peran per aplikasi.
- **Aplikasi bawahan** (Absensi, Website, nanti BK/TU/PKL) punya database sendiri. Mereka **mengambil** data induk dari Pusat dan **login lewat Pusat** (satu akun untuk semua).
- Data induk **diubah hanya di Pusat**. Data milik aplikasi (absensi, nilai, berita, dst.) tetap di aplikasinya masing-masing.

## A. Pertama kali (urutan wajib)
1. **Pusat**: pasang, buat superadmin (`pusat:buat-admin`), login di `/login`.
2. **Daftarkan aplikasi** di Pusat (`pusat:aplikasi-baru` untuk server, `pusat:siapkan-lokal` untuk uji lokal; atau panel **Administrasi → Aplikasi**). Salin `client_id`, `client_secret`, `webhook_secret` ke `.env` aplikasi tersebut (`PUSAT_ENABLED=true`, `PUSAT_URL`, dst.).
3. **Isi data induk di Pusat**, urut: **Tahun Ajaran → Jurusan → Guru & Staf → Kelas → Siswa**.
   Manual lewat menu masing-masing, atau massal lewat **Impor CSV** (selalu ada pratinjau sebelum diterapkan; urutan impor: guru → kelas → siswa).
4. **Buat akun & beri peran**: **Administrasi → Akun → Baru**. Isi username/nama/email/password, kaitkan ke guru/siswa bila perlu, lalu **centang peran per aplikasi** (mis. Absensi: `guru`, `kepsek`, `bk`, `tu`, `admin`; Website: `admin`).
   Tanpa peran di sebuah aplikasi, akun **tidak bisa masuk** ke aplikasi itu. Superadmin Pusat otomatis `admin` di semua aplikasi.
5. **Sambungkan tiap aplikasi** (lihat bagian B dan C).

## B. Absensi (aplikasi bawahan)
**Menyambung (sekali):** Admin Absensi → **Pusat** → *Uji koneksi* → *Periksa* (simulasi, tidak mengubah apa pun) → baca ringkasannya → *Terapkan*.
- Kelas dan siswa dari Pusat dibuat/ditautkan; siswa yang hilang di Pusat hanya diberi status `keluar` (tidak ada yang dihapus).
- Pengaman: bila Pusat kosong atau akan menonaktifkan banyak siswa sekaligus, penerapan ditolak kecuali dicentang *tetap terapkan*.

**Sehari-hari:**
| Tugas | Di mana |
|---|---|
| Login | Halaman login → **Masuk lewat Pusat** (login username/password lama tetap ada sebagai cadangan) |
| Ada siswa/kelas baru atau pindah | Ubah **di Pusat**, lalu di Absensi: Admin → Pusat → *Periksa* → *Terapkan* (webhook hanya menandai "ada perubahan menunggu"; atau aktifkan `PUSAT_SINKRON_OTOMATIS=true` untuk tiap jam) |
| Guru baru | Buat di Pusat (Guru & Staf + Akun + peran Absensi). Saat login pertama akun lokal dibuat otomatis; **mapel, pasangan guru–mapel–kelas, dan wali kelas diatur di Absensi** (Admin → Akun Guru) |
| Guru berhenti | Nonaktifkan akunnya di Pusat; akses di Absensi dicabut seketika |
| Absensi, nilai, catatan BK, surat | Tetap di Absensi seperti biasa |

## C. Website
**Menyambung:** isi `.env` Website (`PUSAT_*`), `php artisan migrate`. Email admin di Pusat harus sama dengan email admin lokal (penautan pertama lewat email), dan akun itu perlu peran **admin** untuk aplikasi "web".
**Hasilnya:** tombol *Masuk lewat Pusat* di `/login`; angka siswa & guru di beranda otomatis dari Pusat (cadangan: angka manual di Pengaturan); kartu peringatan di dashboard bila ada jurusan di Pusat yang belum ada di Program Keahlian.
Berita, pengumuman, agenda, galeri tetap dikelola di Website.

## D. Pengelolaan rutin di Pusat
| Tugas | Menu |
|---|---|
| Naik kelas / ganti tahun ajaran | **Tahun Ajaran** (buat baru, tandai aktif) → **Kelas** baru → pindahkan siswa lewat **Siswa** atau Impor CSV (kolom `kelas`) |
| Siswa lulus/pindah/keluar | **Siswa** → ubah *status* (akun login siswa otomatis nonaktif) |
| Reset password / nonaktifkan akun | **Akun** → ubah |
| Kredensial aplikasi hilang | **Aplikasi** → *Rotasi rahasia* (yang lama langsung tidak berlaku; perbarui `.env` aplikasi) |
| Cek hubungan ke aplikasi | **Aplikasi** → *Ping* |
| Perubahan tidak sampai ke aplikasi | **Webhook** → lihat antrean/galat → *Kirim ulang* (cron `schedule:run` harus jalan) |
| Siapa mengubah apa | **Audit** |

## E. Masalah yang sering muncul
| Gejala | Penyebab / solusi |
|---|---|
| "Anda tidak punya akses" setelah login Pusat | Akun belum diberi peran untuk aplikasi itu (Akun → centang peran) |
| `redirect_uri` tidak cocok | Alamat aplikasi di **Administrasi → Aplikasi** harus sama persis dengan `APP_URL` aplikasi (http/https, port, tanpa garis miring akhir) |
| Aplikasi menampilkan "Pusat tidak dapat dihubungi" | Pusat mati / `PUSAT_URL` salah; pakai login lokal, data lama tetap jalan |
| Data baru belum muncul di Absensi | Jalankan *Periksa → Terapkan* di Admin → Pusat (sinkron manual) |
| Setelah dicabut lalu diberi akses lagi, akun lokal tetap nonaktif | Aktifkan manual di aplikasinya (Absensi: Admin → Akun Guru) |

Rincian teknis: `docs/integrasi.md`, `docs/api.md`; Absensi: `docs/PUSAT.md` di repo absensi-pusat; Website: `docs/PUSAT.md` di repo web-pusat.
