<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('judul', 'Beranda') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/pusat.css') }}?v={{ @filemtime(public_path('css/pusat.css')) }}">
    <script src="{{ asset('js/pusat.js') }}?v={{ @filemtime(public_path('js/pusat.js')) }}" defer></script>
</head>
@php
    $saya = auth()->user();
    $admin = $saya?->superadmin && request()->routeIs('admin.*');
    $namaSekolah = \App\Models\Pengaturan::ambil('nama');
    $adaLogo = \App\Services\ProfilSekolah::logoPath() !== null;
    $menu = [
        '' => [
            ['Ringkasan', route('admin.dashboard'), request()->routeIs('admin.dashboard')],
        ],
        'Data induk' => [
            ['Siswa', route('admin.master.index', 'siswa'), request()->is('admin/data/siswa*')],
            ['Guru & Staf', route('admin.master.index', 'pegawai'), request()->is('admin/data/pegawai*')],
            ['Kelas', route('admin.master.index', 'kelas'), request()->is('admin/data/kelas*')],
            ['Jurusan', route('admin.master.index', 'jurusan'), request()->is('admin/data/jurusan*')],
            ['Mata Pelajaran', route('admin.master.index', 'mapel'), request()->is('admin/data/mapel*')],
            ['Pengampu Mapel', route('admin.pengampu.index'), request()->routeIs('admin.pengampu.*')],
            ['Tahun Ajaran', route('admin.master.index', 'tahun-ajaran'), request()->is('admin/data/tahun-ajaran*')],
        ],
        'Proses' => [
            ['Impor CSV', route('admin.impor.form'), request()->routeIs('admin.impor.*')],
            ['Kenaikan Kelas', route('admin.kenaikan.index'), request()->routeIs('admin.kenaikan.*')],
        ],
        'Akun & akses' => [
            ['Akun', route('admin.akun.index'), request()->routeIs('admin.akun.*')],
        ],
        'Integrasi' => [
            ['Aplikasi', route('admin.aplikasi.index'), request()->routeIs('admin.aplikasi.*')],
            ['Sambungan', route('admin.sambungan.index'), request()->routeIs('admin.sambungan.*')],
            ['Webhook', route('admin.webhook.index'), request()->routeIs('admin.webhook.*')],
        ],
        'Sekolah' => [
            ['Profil Sekolah', route('admin.profil.edit'), request()->routeIs('admin.profil.*')],
            ['Audit', route('admin.audit'), request()->routeIs('admin.audit')],
        ],
    ];
@endphp
<body @class(['dengan-sisi' => $admin])>
<a class="lompat" href="#isi">Lompat ke isi</a>
<header class="bar">
    <div class="bar-isi">
        @if ($admin)
            <button type="button" class="tombol-menu" data-aksi="menu" aria-controls="sisi" aria-expanded="false" aria-label="Buka menu">
                <span></span><span></span><span></span>
            </button>
        @endif
        <a class="merek" href="{{ route('portal') }}">
            @if ($adaLogo)<img src="{{ route('logo.sekolah') }}" alt="" width="30" height="30">@else<span class="merek-ikon" aria-hidden="true">P</span>@endif
            <span class="merek-teks">Pusat <b>Sekolah</b>@if ($namaSekolah)<small>{{ $namaSekolah }}</small>@endif</span>
        </a>
        <nav class="nav-utama" aria-label="Menu utama">
            <a href="{{ route('portal') }}" @class(['aktif' => request()->routeIs('portal')])>Aplikasi saya</a>
            @if ($saya?->superadmin)
                <a href="{{ route('admin.dashboard') }}" @class(['aktif' => request()->routeIs('admin.*')])>Administrasi</a>
            @endif
        </nav>
        @if ($saya)
            <details class="akun-menu">
                <summary><span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($saya->nama, 0, 1)) }}</span><span class="akun-nama">{{ $saya->nama }}</span></summary>
                <div class="akun-isi">
                    <div class="akun-kepala"><strong>{{ $saya->nama }}</strong><span class="samar">{{ $saya->username }}{{ $saya->superadmin ? ' · superadmin' : '' }}</span></div>
                    <a class="hanya-hp" href="{{ route('portal') }}">Aplikasi saya</a>
                    @if ($saya->superadmin)<a class="hanya-hp" href="{{ route('admin.dashboard') }}">Administrasi</a>@endif
                    <a href="{{ route('profil.password') }}">Ganti password</a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form>
                </div>
            </details>
        @endif
    </div>
</header>

@if ($admin)
    <div class="kerangka">
        <aside class="sisi" id="sisi" aria-label="Menu administrasi">
            <nav>
                @foreach ($menu as $grup => $butir)
                    @if ($grup !== '')<div class="sisi-grup">{{ $grup }}</div>@endif
                    @foreach ($butir as [$label, $url, $aktif])
                        <a href="{{ $url }}" @class(['aktif' => $aktif]) @if ($aktif) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                @endforeach
            </nav>
        </aside>
        <div class="sisi-lapis" data-aksi="menu" aria-hidden="true"></div>
        <main class="wadah" id="isi">
            @include('layouts.pesan')
            @yield('isi')
        </main>
    </div>
@else
    <main class="wadah" id="isi">
        @include('layouts.pesan')
        @yield('isi')
    </main>
@endif
</body>
</html>
