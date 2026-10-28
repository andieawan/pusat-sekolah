@extends('layouts.app')

@section('judul', 'Ringkasan')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Ringkasan</h1>
            <p class="samar">Tahun ajaran aktif: <strong>{{ $tahun?->nama ?? 'belum diatur' }}</strong>@if ($tahun) · semester {{ $tahun->semester }}@endif</p>
        </div>
        <div class="aksi">
            <a class="tombol sekunder" href="{{ route('admin.impor.form') }}">Impor CSV</a>
            <a class="tombol" href="{{ route('admin.master.create', 'siswa') }}">+ Siswa</a>
        </div>
    </div>

    <div class="grid">
        @foreach ($hitung as [$label, $jumlah, $url])
            <a class="stat" href="{{ $url }}">
                <div class="angka">{{ number_format($jumlah, 0, ',', '.') }}</div>
                <div class="samar">{{ trim($label) }}</div>
            </a>
        @endforeach
    </div>

    @php($belum = collect($tugas)->where(0, false)->count())
    <div class="kartu">
        <div class="kartu-kepala">
            <h2>Daftar periksa</h2>
            <span class="lencana {{ $belum ? 'warn' : 'ok' }}">{{ $belum ? $belum.' perlu dikerjakan' : 'semua beres' }}</span>
        </div>
        <ul class="tugas">
            @foreach ($tugas as [$ok, $judul, $ket, $url, $tombol])
                <li>
                    <span class="ikon {{ $ok ? 'ok' : 'warn' }}" aria-hidden="true">{{ $ok ? '✓' : '!' }}</span>
                    <div class="teks"><strong>{{ $judul }}</strong><span class="samar">{{ $ket }}</span></div>
                    @unless ($ok)<a class="tombol sekunder kecil" href="{{ $url }}">{{ $tombol }}</a>@endunless
                </li>
            @endforeach
        </ul>
    </div>

    @if (auth()->user()->superadmin)
    <div class="kartu">
        <div class="kartu-kepala">
            <h2>Aplikasi terhubung</h2>
            <a class="tombol sekunder kecil" href="{{ route('admin.aplikasi.index') }}">Kelola aplikasi</a>
        </div>
        @if ($aplikasi->isEmpty())
            <p class="samar">Belum ada aplikasi terdaftar. <a href="{{ route('admin.aplikasi.create') }}">Daftarkan aplikasi pertama</a>.</p>
        @else
            <div class="gulir">
                <table class="tabel hp-kartu">
                    <thead>
                    <tr><th>Aplikasi</th><th>Status</th><th>Akun berperan</th><th>Kabar tertunda</th><th>Gagal</th><th>Terakhir terkirim</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($aplikasi as $a)
                        <tr>
                            <td><a href="{{ route('admin.aplikasi.edit', $a) }}">{{ $a->nama }}</a> <span class="samar">{{ $a->kode }}</span></td>
                            <td data-label="Status"><span class="lencana {{ $a->aktif ? 'ok' : 'warn' }}">{{ $a->aktif ? 'aktif' : 'nonaktif' }}</span></td>
                            <td data-label="Akun berperan">{{ $a->akses_count }}</td>
                            <td data-label="Tertunda">{{ $a->outbox_pending }}</td>
                            <td data-label="Gagal">
                                @if ($a->outbox_gagal > 0)
                                    <a href="{{ route('admin.webhook.index', ['status' => 'gagal', 'aplikasi' => $a->id]) }}"><span class="lencana bad">{{ $a->outbox_gagal }}</span></a>
                                @else
                                    0
                                @endif
                            </td>
                            <td data-label="Terakhir">{{ $a->terakhir_kirim ? \Illuminate\Support\Carbon::parse($a->terakhir_kirim)->locale('id')->diffForHumans() : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endif
@endsection
