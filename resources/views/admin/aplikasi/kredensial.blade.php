@extends('layouts.app')

@section('judul', $judul)

@section('isi')
    <div class="kepala"><h1>{{ $judul }}: {{ $app->nama }}</h1></div>

    <div class="notif info">
        <strong>Salin dan simpan sekarang.</strong> Rahasia di bawah tidak akan ditampilkan lagi
        (client secret hanya disimpan sebagai hash). Jangan kirim lewat chat umum atau commit ke Git.
        Menyegarkan halaman ini tidak akan menampilkannya kembali.
    </div>

    <div class="kartu">
        <div class="baris">
            <label for="k-id">PUSAT_CLIENT_ID</label>
            <div class="baris-input">
                <input type="text" id="k-id" class="mono" value="{{ $clientId }}" readonly>
                <button class="tombol sekunder kecil" type="button" data-aksi="salin" data-target="#k-id">Salin</button>
            </div>
        </div>
        @if ($secret)
            <div class="baris">
                <label for="k-secret">PUSAT_CLIENT_SECRET</label>
                <div class="baris-input">
                    <input type="text" id="k-secret" class="mono" value="{{ $secret }}" readonly>
                    <button class="tombol sekunder kecil" type="button" data-aksi="salin" data-target="#k-secret">Salin</button>
                </div>
            </div>
        @endif
        @if ($webhookSecret)
            <div class="baris">
                <label for="k-wh">PUSAT_WEBHOOK_SECRET</label>
                <div class="baris-input">
                    <input type="text" id="k-wh" class="mono" value="{{ $webhookSecret }}" readonly>
                    <button class="tombol sekunder kecil" type="button" data-aksi="salin" data-target="#k-wh">Salin</button>
                </div>
            </div>
        @endif
        <div class="baris">
            <span class="label">Alamat Pusat untuk SSO dan API</span>
            <div class="mono">{{ url('/') }}</div>
        </div>
    </div>

    <a class="tombol" href="{{ route('admin.aplikasi.edit', $app) }}">Selesai, kembali ke pengaturan aplikasi</a>
@endsection
