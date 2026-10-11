@extends('layouts.polos')

@section('judul', 'Permintaan tidak valid')

@section('isi')
    <h1>Tidak bisa melanjutkan</h1>
    <p class="sub">{{ $pesan }}</p>
    <a class="tombol sekunder" href="{{ route('login') }}">Ke halaman masuk</a>
@endsection
