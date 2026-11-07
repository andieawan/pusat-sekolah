@extends('layouts.app')

@section('judul', 'Notifikasi')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Notifikasi</h1>
            <p class="samar">Laporan dari aplikasi: selesai sinkron setelah diminta Pusat atau disinkron manual, sinkron gagal, dan pulih kembali.</p>
        </div>
        @if ($items->whereNull('dibaca_pada')->isNotEmpty())
            <div class="aksi">
                <form method="post" action="{{ route('admin.notifikasi.baca') }}">
                    @csrf
                    <button class="tombol sekunder" type="submit">Tandai semua dibaca</button>
                </form>
            </div>
        @endif
    </div>

    <div class="kartu">
        @forelse ($items as $n)
            <div @class(['notif-baris', 'belum' => ! $n->dibaca_pada])>
                <span class="lencana {{ $n->jenis === 'galat' ? 'galat' : 'ok' }}">{{ $n->jenis === 'galat' ? 'gagal' : 'selesai' }}</span>
                <div>
                    <strong>{{ $n->judul }}</strong>
                    @if ($n->isi)<div>{{ $n->isi }}</div>@endif
                    <div class="samar">{{ $n->created_at->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</div>
                </div>
            </div>
        @empty
            <p class="samar">Belum ada notifikasi. Tekan <a href="{{ route('admin.aplikasi.index') }}">Sinkronkan</a> di menu Aplikasi — laporan dari aplikasi muncul di sini dan di pojok layar.</p>
        @endforelse
    </div>
    {{ $items->links() }}
@endsection
