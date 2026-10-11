@extends('layouts.app')

@section('judul', 'Audit')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Log audit</h1>
            <p class="samar">Siapa mengubah apa. Disimpan 1 tahun. Password dan rahasia tidak pernah dicatat.</p>
        </div>
    </div>

    <form class="pencarian" method="get" action="{{ route('admin.audit') }}">
        <input type="search" name="aksi" value="{{ request('aksi') }}" placeholder="Awalan aksi, mis. siswa. atau login" aria-label="Aksi">
        <button class="tombol sekunder" type="submit">Filter</button>
    </form>

    <div class="kartu">
        <div class="gulir">
            <table class="tabel">
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Detail</th><th>IP</th></tr></thead>
                <tbody>
                @forelse ($items as $a)
                    <tr>
                        <td>{{ $a->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $a->user?->username ?? '—' }}</td>
                        <td class="mono">{{ $a->aksi }}</td>
                        <td class="mono">{{ $a->objek }}</td>
                        <td class="mono">{{ $a->detail ? json_encode($a->detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}</td>
                        <td>{{ $a->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="samar">Belum ada catatan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links('pagination.sederhana') }}
    </div>
@endsection
