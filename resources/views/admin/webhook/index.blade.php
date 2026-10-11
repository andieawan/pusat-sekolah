@extends('layouts.app')

@section('judul', 'Webhook')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Antrean webhook</h1>
            <p class="samar">
                Tertunda: {{ $hitung['pending'] ?? 0 }} ·
                Terkirim: {{ $hitung['terkirim'] ?? 0 }} ·
                Gagal: {{ $hitung['gagal'] ?? 0 }}
            </p>
        </div>
        <div class="aksi">
            <form method="post" action="{{ route('admin.webhook.kirim') }}">
                @csrf
                <button class="tombol sekunder" type="submit">Kirim yang jatuh tempo sekarang</button>
            </form>
            <form method="post" action="{{ route('admin.webhook.ulang_semua') }}" data-konfirmasi="Jadwalkan ulang semua entri yang gagal?">
                @csrf
                <button class="tombol" type="submit">Ulangi semua yang gagal</button>
            </form>
        </div>
    </div>

    <form class="pencarian" method="get" action="{{ route('admin.webhook.index') }}">
        <select name="status" aria-label="Status">
            <option value="">Semua status</option>
            @foreach (['pending' => 'Tertunda', 'terkirim' => 'Terkirim', 'gagal' => 'Gagal'] as $k => $t)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $t }}</option>
            @endforeach
        </select>
        <select name="aplikasi" aria-label="Aplikasi">
            <option value="">Semua aplikasi</option>
            @foreach ($aplikasi as $id => $nama)
                <option value="{{ $id }}" @selected((string) request('aplikasi') === (string) $id)>{{ $nama }}</option>
            @endforeach
        </select>
        <button class="tombol sekunder" type="submit">Filter</button>
    </form>

    <div class="kartu">
        <div class="gulir">
            <table class="tabel">
                <thead><tr><th>#</th><th>Aplikasi</th><th>Event</th><th>Status</th><th>Percobaan</th><th>Berikutnya</th><th>Hasil</th><th></th></tr></thead>
                <tbody>
                @forelse ($items as $o)
                    <tr>
                        <td><a href="{{ route('admin.webhook.show', $o) }}">{{ $o->id }}</a></td>
                        <td>{{ $o->aplikasi?->kode }}</td>
                        <td class="mono">{{ $o->event }}</td>
                        <td><span class="lencana {{ ['terkirim' => 'ok', 'pending' => 'warn', 'gagal' => 'bad'][$o->status] ?? '' }}">{{ $o->status }}</span></td>
                        <td>{{ $o->percobaan }}</td>
                        <td>{{ $o->jadwal_berikutnya?->format('d/m H:i') ?? '—' }}</td>
                        <td class="samar">{{ $o->galat_terakhir ?: ($o->kode_http ? 'HTTP '.$o->kode_http : '') }}</td>
                        <td class="kanan">
                            @if ($o->status !== 'terkirim')
                                <form method="post" action="{{ route('admin.webhook.ulang', $o) }}">
                                    @csrf
                                    <button class="tombol sekunder kecil" type="submit">Kirim ulang</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="samar">Tidak ada entri.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links('pagination.sederhana') }}
    </div>
@endsection
