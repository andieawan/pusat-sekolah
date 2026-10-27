@extends('layouts.app')

@section('judul', 'Ganti password')

@section('isi')
    <div class="kepala"><h1>Ganti password</h1></div>

    <div class="kartu">
        <form class="form" method="post" action="{{ route('profil.password.ubah') }}">
            @csrf
            @method('PUT')
            <div class="baris">
                <label for="password_lama">Password saat ini</label>
                <input type="password" id="password_lama" name="password_lama" required autocomplete="current-password">
            </div>
            <div class="baris">
                <label for="password">Password baru</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
                <div class="samar">{{ \App\Support\AturanPassword::keterangan() }}</div>
            </div>
            <div class="baris">
                <label for="password_confirmation">Ulangi password baru</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="tombol" type="submit">Simpan password</button>
            <p class="samar">Setelah diganti, Anda perlu membuka ulang aplikasi lain dari portal.</p>
        </form>
    </div>
@endsection
