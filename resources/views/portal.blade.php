@extends('layouts.app')

@section('judul', 'Aplikasi saya')

@php
    $info = [
        'absensi' => ['✅', 'Absensi siswa harian, nilai, dan rekap kehadiran.'],
        'bk' => ['💬', 'Catatan bimbingan, kasus, home visit, dan surat BK.'],
        'pkl' => ['🏭', 'Penempatan, absen, jurnal, dan nilai PKL.'],
        'tu' => ['🗂️', 'Surat masuk/keluar, mutasi siswa, absen pegawai.'],
        'web' => ['🌐', 'Kelola berita, galeri, dan profil di website sekolah.'],
    ];
    $jam = (int) now()->setTimezone('Asia/Jakarta')->format('G');
    $salam = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));
@endphp

@section('isi')
    <div class="kepala">
        <div>
            <h1>{{ $salam }}, {{ auth()->user()->nama }}</h1>
            <p class="samar">Pilih aplikasi yang ingin dibuka — Anda langsung masuk tanpa login lagi.</p>
        </div>
        @if (auth()->user()->superadmin)
            <a class="tombol sekunder" href="{{ route('admin.dashboard') }}">Buka Administrasi</a>
        @endif
    </div>

    @if ($aplikasi->isEmpty())
        <div class="notif info">
            Akun Anda belum diberi akses ke aplikasi mana pun. Hubungi administrator sekolah.
        </div>
    @else
        <div class="grid-app">
            @foreach ($aplikasi as $item)
                @php([$ikon, $ket] = $info[$item['model']->kode] ?? ['📦', parse_url($item['model']->base_url, PHP_URL_HOST)])
                <a class="app" href="{{ route('buka', $item['model']->kode) }}" rel="noopener">
                    <span class="app-ikon" aria-hidden="true">{{ $ikon }}</span>
                    <span class="app-isi">
                        <strong>{{ $item['model']->nama }}</strong>
                        <span class="ket">{{ $ket }}</span>
                        <span class="peran">
                            @foreach ($item['peran'] as $peran)
                                <span class="lencana">{{ str_replace('_', ' ', $peran) }}</span>
                            @endforeach
                        </span>
                        <span class="buka">Buka →</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
@endsection
