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
        'ringkasan' => 'Ringkasan statistik (aman untuk publik)',
    ],

    // Nilai awal saat mendaftarkan aplikasi baru di panel admin.
    'aplikasi_bawaan' => [
        'absensi' => [
            'nama' => 'Absensi Siswa',
            'webhook_path' => '/api/pusat/webhook', // klien Absensi menerima di route API
            'peran' => ['guru', 'wali_kelas', 'kepsek', 'bk', 'tu', 'admin', 'orang_tua', 'siswa'],
            'scope' => ['tahun_ajaran', 'jurusan', 'kelas', 'siswa', 'pegawai', 'akun'],
        ],
        'bk' => [
            'nama' => 'Bimbingan Konseling',
            'peran' => ['guru_bk', 'wali_kelas', 'kepsek', 'admin'],
            'scope' => ['tahun_ajaran', 'jurusan', 'kelas', 'siswa', 'pegawai_dasar', 'akun'],
        ],
        'tu' => [
            'nama' => 'Tata Usaha',
            'peran' => ['staf_tu', 'kepsek', 'admin'],
            'scope' => ['tahun_ajaran', 'jurusan', 'kelas', 'siswa', 'pegawai', 'akun'],
        ],
        'pkl' => [
            'nama' => 'Manajemen PKL',
            'peran' => ['admin', 'koordinator', 'guru_pembimbing', 'siswa'],
            'scope' => ['tahun_ajaran', 'jurusan', 'kelas', 'siswa_dasar', 'pegawai_dasar', 'akun'],
        ],
        'web' => [
            'nama' => 'Website Sekolah',
            'peran' => ['admin', 'editor'],
            'scope' => ['jurusan', 'ringkasan', 'akun'],
        ],
    ],
];
