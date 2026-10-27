@extends('layouts.polos')

@section('judul', 'Masuk')

@section('isi')
    @if (\App\Services\ProfilSekolah::logoPath())<img class="logo" src="{{ route('logo.sekolah') }}" alt="">@endif
    <h1>Pusat Sekolah</h1>
    <p class="sub">{{ \App\Models\Pengaturan::ambil('nama', 'Satu akun untuk semua aplikasi sekolah') }}</p>

    <form method="post" action="{{ route('login.proses') }}">
        @csrf
        <div class="baris">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" required autofocus
                   autocomplete="username" autocapitalize="none" spellcheck="false">
            @error('username') <div class="galat-input">{{ $message }}</div> @enderror
        </div>
        <div class="baris">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            @error('password') <div class="galat-input">{{ $message }}</div> @enderror
        </div>
        <button class="tombol" type="submit">Masuk</button>
    </form>
    <p class="samar kanan">Lupa password? Hubungi administrator sekolah.</p>
@endsection
