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
<body>
<header class="bar">
    <div class="bar-isi">
        <a class="merek" href="{{ route('portal') }}">Pusat <span>Sekolah</span></a>
        <nav aria-label="Menu utama">
            <a href="{{ route('portal') }}" @class(['aktif' => request()->routeIs('portal')])>Aplikasi saya</a>
            @if (auth()->user()?->superadmin)
                <a href="{{ route('admin.dashboard') }}" @class(['aktif' => request()->routeIs('admin.*')])>Administrasi</a>
            @endif
        </nav>
        <div class="pengguna">
            <a href="{{ route('profil.password') }}" class="samar-terang">{{ auth()->user()?->nama }}</a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="tombol sekunder kecil" type="submit">Keluar</button>
            </form>
        </div>
    </div>
</header>

@if (auth()->user()?->superadmin && request()->routeIs('admin.*'))
    <div class="subnav">
        <div class="bar-isi">
            <a href="{{ route('admin.dashboard') }}" @class(['aktif' => request()->routeIs('admin.dashboard')])>Ringkasan</a>
            <a href="{{ route('admin.master.index', 'siswa') }}" @class(['aktif' => request()->is('admin/data/siswa*')])>Siswa</a>
            <a href="{{ route('admin.master.index', 'pegawai') }}" @class(['aktif' => request()->is('admin/data/pegawai*')])>Guru &amp; Staf</a>
            <a href="{{ route('admin.master.index', 'kelas') }}" @class(['aktif' => request()->is('admin/data/kelas*')])>Kelas</a>
            <a href="{{ route('admin.master.index', 'jurusan') }}" @class(['aktif' => request()->is('admin/data/jurusan*')])>Jurusan</a>
            <a href="{{ route('admin.master.index', 'tahun-ajaran') }}" @class(['aktif' => request()->is('admin/data/tahun-ajaran*')])>Tahun Ajaran</a>
            <a href="{{ route('admin.impor.form') }}" @class(['aktif' => request()->routeIs('admin.impor.*')])>Impor CSV</a>
            <a href="{{ route('admin.akun.index') }}" @class(['aktif' => request()->routeIs('admin.akun.*')])>Akun</a>
            <a href="{{ route('admin.aplikasi.index') }}" @class(['aktif' => request()->routeIs('admin.aplikasi.*')])>Aplikasi</a>
            <a href="{{ route('admin.webhook.index') }}" @class(['aktif' => request()->routeIs('admin.webhook.*')])>Webhook</a>
            <a href="{{ route('admin.sambungan.index') }}" @class(['aktif' => request()->routeIs('admin.sambungan.*')])>Sambungan</a>
            <a href="{{ route('admin.audit') }}" @class(['aktif' => request()->routeIs('admin.audit')])>Audit</a>
        </div>
    </div>
@endif

<main class="wadah">
    @include('layouts.pesan')
    @yield('isi')
</main>
</body>
</html>
