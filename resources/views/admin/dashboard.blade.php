@extends('layouts.app')

@section('judul', 'Ringkasan')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Ringkasan</h1>
            <p class="samar">Tahun ajaran aktif: <strong>{{ $tahun?->nama ?? 'belum diatur' }}</strong></p>
        </div>
    </div>

    @unless ($tahun)
        <div class="notif info">
            Belum ada tahun ajaran aktif. <a href="{{ route('admin.master.create', 'tahun-ajaran') }}">Buat sekarang</a>
            (diperlukan untuk kelas dan impor siswa).
        </div>
    @endunless

    <div class="grid">
        @foreach ($hitung as $label => $jumlah)
            <div class="kartu">
                <div class="angka">{{ number_format($jumlah, 0, ',', '.') }}</div>
                <div class="samar">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    @if ($tanpaAkses > 0)
        <div class="notif info">
            {{ $tanpaAkses }} akun aktif belum punya akses ke aplikasi mana pun.
            <a href="{{ route('admin.akun.index') }}">Lihat akun</a>
        </div>
    @endif

    <div class="kartu">
        <h2>Aplikasi terhubung</h2>
        @if ($aplikasi->isEmpty())
            <p class="samar">Belum ada aplikasi terdaftar. <a href="{{ route('admin.aplikasi.create') }}">Daftarkan aplikasi pertama</a>.</p>
        @else
            <div class="gulir">
                <table class="tabel">
                    <thead>
                    <tr><th>Aplikasi</th><th>Status</th><th>Akun/peran</th><th>Webhook tertunda</th><th>Gagal</th><th>Terakhir terkirim</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($aplikasi as $a)
                        <tr>
                            <td><a href="{{ route('admin.aplikasi.edit', $a) }}">{{ $a->nama }}</a><div class="samar">{{ $a->kode }}</div></td>
                            <td><span class="lencana {{ $a->aktif ? 'ok' : 'warn' }}">{{ $a->aktif ? 'aktif' : 'nonaktif' }}</span></td>
                            <td>{{ $a->akses_count }}</td>
                            <td>{{ $a->outbox_pending }}</td>
                            <td>
                                @if ($a->outbox_gagal > 0)
                                    <a href="{{ route('admin.webhook.index', ['status' => 'gagal', 'aplikasi' => $a->id]) }}"><span class="lencana bad">{{ $a->outbox_gagal }}</span></a>
                                @else
                                    0
                                @endif
                            </td>
                            <td>{{ $a->terakhir_kirim ? \Illuminate\Support\Carbon::parse($a->terakhir_kirim)->diffForHumans() : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
