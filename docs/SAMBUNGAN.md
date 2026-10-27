# Sambungan Antar Aplikasi

Semua aplikasi sudah tersambung ke **Pusat** (login tunggal, data induk kelas/siswa/guru lewat webhook & sinkron).
Sambungan **langsung antar aplikasi** diatur dari satu menu: **Pusat → Administrasi → Sambungan**.
Tidak perlu membuat kunci atau mengubah `.env`.

## Cara menyambungkan

1. Perbarui semua aplikasi (sekali saja): di setiap VM `sudo bash deploy/update.sh`.
2. Buka Pusat → **Administrasi → Sambungan**.
3. Klik **Sambungkan** pada sambungan yang diinginkan. Pusat membuat kunci rahasia dan mengabari kedua aplikasi.
   Kolom *Kabar terakhir* harus menjadi **diterima** untuk kedua aplikasi.
4. Buka halaman **Admin → Integrasi** di aplikasi peminta, klik **Uji koneksi**.

| Sambungan | Hasilnya |
|---|---|
| Absensi → BK | Menu **Presensi** di BK (rekap kehadiran, absensi manual, tautan delegasi ketua kelas). Kunci otomatis muncul di Absensi → Integrasi API sebagai "BK (Sambungan Pusat)". |
| PKL → BK | Menu **PKL** di BK: siswa PKL yang sering alpa/tanpa keterangan atau jurnal tertunda, riwayat PKL per siswa |
| PKL ↔ TU | Surat pengantar/penarikan PKL bernomor dari register **Surat Keluar TU** (kode PKL) dan tercatat di TU |
| PKL → Website | Halaman **Akademik → Mitra Industri (DUDI)**: nama & bidang DUDI, jumlah siswa (tanpa data siswa) |
| Menu pindah aplikasi | Otomatis: menu akun **Pindah ke aplikasi lain** di semua aplikasi setelah login lewat Pusat |

Tombol lain:
- **Ganti kunci** — kunci lama langsung tidak berlaku, kunci baru dikirim ke kedua aplikasi.
- **Putuskan** — sambungan dimatikan di kedua aplikasi.
- **Kirim ulang** — bila salah satu aplikasi tadi mati/belum diperbarui. Bisa juga klik **Sinkron sekarang** di halaman Integrasi aplikasi itu.

## Cara kerjanya (untuk teknisi)

- Pusat menyimpan kunci terenkripsi (tabel `sambungan`). Webhook `sambungan.diperbarui` hanya berisi nama sambungan, **tanpa kunci**.
- Aplikasi lalu mengambil sendiri `GET /api/v1/sambungan` (Basic client_id:client_secret) dan menyimpannya terenkripsi di
  `storage/app/sambungan-pusat.json`. Aplikasi juga mengambilnya saat sinkron (tiap jam / tombol Sinkron sekarang).
- Nilai dari Pusat menimpa `.env` (`PKL_API_KUNCI_BK`, `PKL_URL`, `PKL_API_KEY`, `ABSENSI_URL`, `ABSENSI_API_KEY`, `TU_API_KUNCI_PKL`,
  `TU_URL`, `TU_API_KEY`, `PKL_MITRA_PUBLIK`). Selama sebuah sambungan belum pernah diatur dari Pusat, nilai `.env` tetap dipakai.
- Kunci hanya berlaku satu arah dan satu keperluan (BK hanya bisa *membaca* data PKL; PKL hanya bisa *meminta nomor surat* di TU).
- Bila aplikasi tujuan mati: BK menampilkan pesan galat, PKL bisa mengisi nomor surat manual, Website memakai daftar mitra terakhir (maks. 7 hari).
- Absensi harian kelas sengaja tidak diubah oleh PKL.
