@extends('layouts.app')

@section('judul', 'Aplikasi saya')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Halo, {{ auth()->user()->nama }}</h1>
            <p class="samar">Pilih aplikasi yang ingin dibuka. Anda tidak perlu login lagi di masing-masing aplikasi.</p>
        </div>
    </div>

    @if ($aplikasi->isEmpty())
        <div class="notif info">
            Akun Anda belum diberi akses ke aplikasi mana pun. Hubungi administrator sekolah.
        </div>
    @else
        <div class="grid">
            @foreach ($aplikasi as $item)
                <a class="app" href="{{ route('buka', $item['model']->kode) }}" rel="noopener">
                    <strong>{{ $item['model']->nama }}</strong>
                    <span class="samar">{{ parse_url($item['model']->base_url, PHP_URL_HOST) }}</span>
                    <div class="peran">
                        @foreach ($item['peran'] as $peran)
                            <span class="lencana">{{ $peran }}</span>
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
