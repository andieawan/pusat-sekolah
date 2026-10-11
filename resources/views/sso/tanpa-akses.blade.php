@extends('layouts.polos')

@section('judul', 'Tidak punya akses')

@section('isi')
    <h1>Akses ditolak</h1>
    <p class="sub">
        Akun <strong>{{ auth()->user()->username }}</strong> belum diberi akses ke
        <strong>{{ $app->nama }}</strong>. Hubungi administrator sekolah.
    </p>
    <a class="tombol sekunder" href="{{ route('portal') }}">Kembali ke portal</a>
@endsection
