@extends('layouts.app')

@section('judul', 'Aplikasi')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Aplikasi terdaftar</h1>
            <p class="samar">Setiap aplikasi punya kredensial sendiri, hak baca data (scope), dan alamat penerima webhook.</p>
        </div>
        <a class="tombol" href="{{ route('admin.aplikasi.create') }}">+ Daftarkan aplikasi</a>
    </div>

    <div class="kartu">
        <div class="gulir">
            <table class="tabel">
                <thead><tr><th>Aplikasi</th><th>Alamat</th><th>Status</th><th>Akun</th><th>Webhook</th><th></th></tr></thead>
                <tbody>
                @forelse ($items as $a)
                    <tr>
                        <td><strong>{{ $a->nama }}</strong><div class="samar">{{ $a->kode }}</div></td>
                        <td class="mono">{{ $a->base_url }}</td>
                        <td><span class="lencana {{ $a->aktif ? 'ok' : 'warn' }}">{{ $a->aktif ? 'aktif' : 'nonaktif' }}</span></td>
                        <td>{{ $a->akses_count }}</td>
                        <td class="mono">{{ $a->webhook_url ?: '—' }}</td>
                        <td class="kanan">
                            <div class="aksi">
                                <a class="tombol sekunder kecil" href="{{ route('admin.aplikasi.edit', $a) }}">Ubah</a>
                                @if ($a->webhook_url)
                                    <form method="post" action="{{ route('admin.aplikasi.ping', $a) }}">
                                        @csrf
                                        <button class="tombol sekunder kecil" type="submit">Uji webhook</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="samar">Belum ada aplikasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
