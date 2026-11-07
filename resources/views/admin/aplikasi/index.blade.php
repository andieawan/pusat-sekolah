@extends('layouts.app')

@section('judul', 'Aplikasi')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Aplikasi terdaftar</h1>
            <p class="samar">Setiap aplikasi punya kredensial sendiri, hak baca data (scope), dan alamat penerima webhook.</p>
        </div>
        <div class="aksi">
            @if ($items->where('aktif', true)->whereNotNull('webhook_url')->isNotEmpty())
                <form method="post" action="{{ route('admin.aplikasi.sinkron') }}">
                    @csrf
                    <button class="tombol sekunder" type="submit" title="Semua aplikasi menarik ulang data dari Pusat dalam ±1 menit">Sinkronkan semua aplikasi</button>
                </form>
            @endif
            <a class="tombol" href="{{ route('admin.aplikasi.create') }}">+ Daftarkan aplikasi</a>
        </div>
    </div>

    <div class="kartu">
        <div class="gulir">
            <table class="tabel hp-kartu">
                <thead><tr><th>Aplikasi</th><th>Alamat</th><th>Status</th><th>Akun</th><th>Webhook</th><th>Sinkron terakhir</th><th></th></tr></thead>
                <tbody>
                @forelse ($items as $a)
                    <tr>
                        <td><strong>{{ $a->nama }}</strong><div class="samar">{{ $a->kode }}</div></td>
                        <td class="mono" data-label="Alamat">{{ $a->base_url }}</td>
                        <td data-label="Status"><span class="lencana {{ $a->aktif ? 'ok' : 'warn' }}">{{ $a->aktif ? 'aktif' : 'nonaktif' }}</span></td>
                        <td data-label="Akun">{{ $a->akses_count }}</td>
                        <td class="mono" data-label="Webhook">{{ $a->webhook_url ?: '—' }}</td>
                        <td data-label="Sinkron terakhir">
                            @php($lap = $a->sinkron_laporan)
                            @if ($a->sinkron_diminta_pada && $a->sinkron_diminta_pada->gt(now()->subMinutes(10)))
                                <span class="lencana warn" data-segarkan-laporan>menunggu laporan…</span>
                                <div class="samar">diminta {{ $a->sinkron_diminta_pada->timezone(config('app.timezone'))->format('H:i') }}</div>
                            @elseif ($a->sinkron_diminta_pada)
                                <span class="lencana warn" title="Aplikasi belum melapor. Pastikan sudah di-Update aplikasi (fitur laporan sinkron) dan jadwal schedule:run berjalan.">belum dilaporkan</span>
                            @endif
                            @if ($lap)
                                <div><span class="lencana {{ $lap['ok'] ? 'ok' : 'galat' }}">{{ $lap['ok'] ? '✓ selesai' : '✗ gagal' }}</span>
                                    <span class="samar">{{ $a->sinkron_lapor_pada?->timezone(config('app.timezone'))->format('d/m H:i') }} · {{ $lap['pemicu'] ?? '' }}</span></div>
                                <div class="samar">{{ $lap['ok'] ? ($lap['ringkas'] ?? '') : ($lap['galat'] ?? '') }}</div>
                            @elseif (! $a->sinkron_diminta_pada)
                                <span class="samar">—</span>
                            @endif
                        </td>
                        <td class="kanan">
                            <div class="aksi">
                                <a class="tombol sekunder kecil" href="{{ route('admin.aplikasi.edit', $a) }}">Ubah</a>
                                @if ($a->webhook_url)
                                    <form method="post" action="{{ route('admin.aplikasi.ping', $a) }}">
                                        @csrf
                                        <button class="tombol sekunder kecil" type="submit">Uji webhook</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.aplikasi.sinkron.satu', $a) }}">
                                        @csrf
                                        <button class="tombol sekunder kecil" type="submit">Sinkronkan</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="samar">Belum ada aplikasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
