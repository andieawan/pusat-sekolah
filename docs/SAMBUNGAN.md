# Sambungan Antar Aplikasi

Semua aplikasi sudah tersambung ke **Pusat** (login tunggal, data induk kelas/siswa/guru lewat webhook & sinkron).
Panduan ini mengatur sambungan **langsung antar aplikasi**. Setiap sambungan memakai satu kunci acak yang sama di kedua sisi.

Buat kunci (di VM mana saja): `openssl rand -hex 32`

| Sambungan | Isi di aplikasi pemberi data | Isi di aplikasi peminta | Hasilnya |
|---|---|---|---|
| BK ↔ Absensi (sudah ada) | Absensi → Admin → Integrasi API: buat kunci `kehadiran:baca`, `kehadiran:tulis` | BK: `ABSENSI_URL`, `ABSENSI_API_KEY` | Menu **Presensi** di BK |
| PKL → BK | PKL: `PKL_API_KUNCI_BK=<kunci>` | BK: `PKL_URL=https://pkl.smkibupakusari.sch.id`, `PKL_API_KEY=<kunci>` | Menu **PKL** di BK: siswa PKL yang sering alpa/tanpa keterangan atau jurnal tertunda, riwayat PKL per siswa |
| PKL ↔ TU | TU: `TU_API_KUNCI_PKL=<kunci>` | PKL: `TU_URL=https://tu.smkibupakusari.sch.id`, `TU_API_KEY=<kunci>` | Surat pengantar/penarikan PKL bernomor dari register **Surat Keluar TU** (kode PKL) dan tercatat di TU |
| PKL → Website | PKL: `PKL_MITRA_PUBLIK=true` (bawaan) | Website: `PKL_URL=https://pkl.smkibupakusari.sch.id` | Halaman **Akademik → Mitra Industri (DUDI)**: nama & bidang DUDI, jumlah siswa (tanpa data siswa) |
| Menu pindah aplikasi | — (otomatis dari Pusat) | — | Menu akun **Pindah ke aplikasi lain** di semua aplikasi; muncul setelah login lewat Pusat |

Setelah mengubah `.env` di sebuah VM:
```bash
cd /var/www/<aplikasi>
sudo -u www-data php artisan config:cache
```
Lalu cek di **Admin → Integrasi** aplikasi itu: setiap sambungan punya tombol **Uji koneksi**.

Catatan:
- Kunci hanya berlaku satu arah dan satu keperluan (BK hanya bisa *membaca* data PKL; PKL hanya bisa *meminta nomor surat* di TU).
- Ganti kunci kapan saja: isi kunci baru di kedua sisi lalu `config:cache`.
- Bila aplikasi tujuan mati: BK menampilkan pesan galat, PKL bisa mengisi nomor surat manual, Website memakai daftar mitra terakhir (maks. 7 hari).
- Absensi harian kelas sengaja tidak diubah oleh PKL.
