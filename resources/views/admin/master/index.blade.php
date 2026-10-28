@extends('layouts.app')

@section('judul', $d['judul'])

@section('isi')
    <div class="kepala">
        <div>
            <h1>{{ $d['judul'] }}</h1>
            <p class="samar">Perubahan di sini otomatis dikabarkan ke aplikasi yang berhak.</p>
        </div>
        <div class="aksi">
            @if (in_array($sumber, ['siswa', 'pegawai', 'kelas', 'mapel'], true))
                <a class="tombol sekunder" href="{{ route('admin.impor.form') }}">Impor CSV</a>
            @endif
            <a class="tombol" href="{{ route('admin.master.create', $sumber) }}">+ Tambah {{ mb_strtolower($d['judul']) }}</a>
        </div>
    </div>

    <form class="pencarian" method="get" action="{{ route('admin.master.index', $sumber) }}" role="search">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari {{ mb_strtolower($d['judul']) }}…" aria-label="Cari {{ $d['judul'] }}">
        @foreach ($filter as $kolom => [$label, $pilihan])
            <select name="{{ $kolom }}" aria-label="{{ $label }}">
                <option value="">{{ $label }}</option>
                @foreach ($pilihan as $nilai => $teks)
                    <option value="{{ $nilai }}" @selected((string) request($kolom) === (string) $nilai)>{{ $teks }}</option>
                @endforeach
            </select>
        @endforeach
        <button class="tombol sekunder" type="submit">Terapkan</button>
        @if (request()->hasAny(array_merge(['q'], array_keys($filter))) && collect(request()->only(array_merge(['q'], array_keys($filter))))->filter()->isNotEmpty())
            <a href="{{ route('admin.master.index', $sumber) }}">Hapus saringan</a>
        @endif
    </form>

    <div class="kartu">
        <p class="jumlah">{{ number_format($total, 0, ',', '.') }} data</p>
        <div class="gulir">
            <table class="tabel hp-kartu">
                <thead>
                <tr>
                    @foreach ($d['kolom'] as $kolom)
                        <th>{{ $kolom[0] }}</th>
                    @endforeach
                    <th><span class="lompat">Aksi</span></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $m)
                    <tr>
                        @foreach ($d['kolom'] as $i => $kolom)
                            <td @if ($i > 0) data-label="{{ $kolom[0] }}" @endif>{{ $kolom[1] instanceof \Closure ? ($kolom[1])($m) : data_get($m, $kolom[1]) }}</td>
                        @endforeach
                        <td class="kanan">
                            <div class="aksi"><a class="tombol sekunder kecil" href="{{ route('admin.master.edit', [$sumber, $m->id]) }}">Ubah</a></div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($d['kolom']) + 1 }}" class="kosong">
                        @if (request('q') || collect(request()->only(array_keys($filter)))->filter()->isNotEmpty())
                            Tidak ada yang cocok dengan pencarian/saringan.
                        @else
                            Belum ada data. <a href="{{ route('admin.master.create', $sumber) }}">Tambah yang pertama</a>@if (in_array($sumber, ['siswa', 'pegawai', 'kelas'], true)) atau <a href="{{ route('admin.impor.form') }}">impor dari CSV</a>@endif.
                        @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links('pagination.sederhana') }}
    </div>
@endsection
