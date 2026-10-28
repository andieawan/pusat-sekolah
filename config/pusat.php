<?php

return [
    // Masa berlaku token akses SSO (detik). Aplikasi bisa meminta ulang diam-diam
    // selama sesi login di Pusat masih aktif.
    'token_ttl' => (int) env('PUSAT_TOKEN_TTL', 3600),

    // Masa berlaku kode otorisasi sekali pakai (detik).
    'kode_ttl' => 60,

    'webhook' => [
        'timeout' => 10,
        'connect_timeout' => 5,
        'batch' => 100,
        // Sertifikat HTTPS aplikasi tujuan. false/ca_bundle hanya untuk uji lokal bersertifikat buatan sendiri.
        'verify_ssl' => filter_var(env('PUSAT_WEBHOOK_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
        'ca_bundle' => env('PUSAT_WEBHOOK_CA_BUNDLE'),
    ],

    // Daftar scope yang bisa diberikan ke aplikasi. Varian "_dasar" hanya memuat
    // data minimal (tanpa nomor HP, tanggal lahir, dst.).
    'scope' => [
        'tahun_ajaran' => 'Tahun ajaran',
        'jurusan' => 'Jurusan',
        'kelas' => 'Kelas',
        'siswa_dasar' => 'Siswa (data dasar)',
        'siswa' => 'Siswa (lengkap)',
        'pegawai_dasar' => 'Guru & staf (data dasar)',
        'pegawai' => 'Guru & staf (lengkap)',
        'akun' => 'Daftar akun & peran untuk aplikasi ini',
        'akun_sandi' => 'Login cadangan: salinan hash password akun (tetap bisa masuk saat Pusat tidak bisa dihubungi)',
        'ringkasan' => 'Ringkasan statistik (aman untuk publik)',
        'siswa_tulis' => 'Tambah siswa & ubah status/kelas (mutasi oleh Tata Usaha)',
        'mapel' => 'Mata pelajaran',
        'pengampu' => 'Pengampu mapel (guru – mapel – kelas)',
        'siswa_kontak' => 'Ubah kontak orang tua siswa (No. HP & nama; oleh wali kelas di Absensi)',
    ],

    // Nilai awal saat mendaftarkan aplikasi baru di panel admin.
    'aplikasi_bawaan' => [
        'absensi' => [
            'nama' => 'Absensi Siswa',
            'webhook_path' => '/api/pusat/webhook', // klien Absensi menerima di route API
            'peran' => ['guru', 'wali_kelas', 'kepsek', 'bk', 'tu', 'admin', 'orang_tua', 'siswa'],
            'scope' => ['tahun_ajaran', 'jurusan', 'kelas', 'siswa', 'pegawai', 'akun', 'akun_sandi', 'siswa_kontak', 'mapel', 'pengampu'],
        ],
        'bk' => [
            'nama' => 'Bimbingan Konseling',
            'webhook_path' => '/api/pusat/webhook',
            // sesuai peta peran aplikasi BK; wali kelas otomatis dari data kelas (cukup peran guru)
            'peran' => ['bk', 'kepsek', 'admin', 'guru'],
            'scope' => ['tahun_ajaran', 'kelas', 'siswa', 'akun', 'akun_sandi'],
        ],
        'tu' => [
            'nama' => 'Tata Usaha',
            'webhook_path' => '/api/pusat/webhook',
            // pegawai = guru/staf yang absen mandiri di aplikasi TU
            'peran' => ['staf_tu', 'kepsek', 'admin', 'pegawai'],
            'scope' => ['tahun_ajaran', 'jurusan', 'kelas', 'siswa', 'pegawai', 'akun', 'akun_sandi', 'siswa_tulis'],
        ],
        'pkl' => [
            'nama' => 'Manajemen PKL',
            'webhook_path' => '/api/pusat/webhook',
            // siswa lengkap: nomor HP siswa untuk dihubungi pembimbing; pembimbing industri memakai akun lokal di aplikasi PKL
            'peran' => ['admin', 'koordinator', 'guru_pembimbing', 'siswa'],
            'scope' => ['tahun_ajaran', 'jurusan', 'kelas', 'siswa', 'pegawai_dasar', 'akun', 'akun_sandi'],
        ],
        'web' => [
            'nama' => 'Website Sekolah',
            'peran' => ['admin', 'editor'],
            'scope' => ['jurusan', 'ringkasan', 'akun', 'akun_sandi'],
        ],
    ],

    // Sambungan antar-aplikasi yang bisa dinyalakan dari menu Administrasi → Sambungan.
    // pemberi = aplikasi yang menyediakan data; peminta = aplikasi yang mengambilnya. Kunci dibuat otomatis oleh Pusat.
    'sambungan' => [
        'absensi-bk' => [
            'nama' => 'Absensi → BK',
            'keterangan' => 'BK membaca rekap kehadiran, mencatat absensi manual, dan membuat tautan delegasi ketua kelas.',
            'pemberi' => 'absensi', 'peminta' => 'bk', 'kunci' => true,
        ],
        'pkl-bk' => [
            'nama' => 'PKL → BK',
            'keterangan' => 'BK melihat siswa PKL yang perlu perhatian (sering alpa / jurnal kosong) dan riwayat PKL per siswa.',
            'pemberi' => 'pkl', 'peminta' => 'bk', 'kunci' => true,
        ],
        'pkl-tu' => [
            'nama' => 'PKL ↔ TU',
            'keterangan' => 'Surat pengantar & penarikan PKL mendapat nomor resmi dari register Surat Keluar TU.',
            'pemberi' => 'tu', 'peminta' => 'pkl', 'kunci' => true,
        ],
        'pkl-web' => [
            'nama' => 'PKL → Website',
            'keterangan' => 'Website menampilkan daftar mitra industri (DUDI) dari aplikasi PKL. Hanya nama & bidang, tanpa data siswa.',
            'pemberi' => 'pkl', 'peminta' => 'web', 'kunci' => false,
        ],
    ],
];
