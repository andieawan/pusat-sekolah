@extends('layouts.app')

@section('judul', $d['judul'])

@section('isi')
    <div class="kepala">
        <div>
            <h1>{{ $d['judul'] }}</h1>
            <p class="samar">Perubahan di sini otomatis dikabarkan ke aplikasi yang berhak (lewat webhook).</p>
        </div>
        <div class="aksi">
            @if (in_array($sumber, ['siswa', 'pegawai'], true))
                <a class="tombol sekunder" href="{{ route('admin.impor.form') }}">Impor CSV</a>
            @endif
            <a class="tombol" href="{{ route('admin.master.create', $sumber) }}">+ Tambah</a>
        </div>
    </div>

    <form class="pencarian" method="get" action="{{ route('admin.master.index', $sumber) }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari…" aria-label="Cari">
        <button class="tombol sekunder" type="submit">Cari</button>
        @if (request('q'))
            <a class="tombol sekunder" href="{{ route('admin.master.index', $sumber) }}">Reset</a>
        @endif
    </form>

    <div class="kartu">
        <div class="gulir">
            <table class="tabel">
                <thead>
                <tr>
                    @foreach ($d['kolom'] as $kolom)
                        <th>{{ $kolom[0] }}</th>
                    @endforeach
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $m)
                    <tr>
                        @foreach ($d['kolom'] as $kolom)
                            <td>{{ $kolom[1] instanceof \Closure ? ($kolom[1])($m) : data_get($m, $kolom[1]) }}</td>
                        @endforeach
                        <td class="kanan">
                            <div class="aksi">
                                <a class="tombol sekunder kecil" href="{{ route('admin.master.edit', [$sumber, $m->id]) }}">Ubah</a>
                                <form method="post" action="{{ route('admin.master.destroy', [$sumber, $m->id]) }}"
                                      data-konfirmasi="Hapus data ini? Aplikasi lain akan diberi tahu.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="tombol bahaya kecil" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($d['kolom']) + 1 }}" class="samar">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links('pagination.sederhana') }}
    </div>
@endsection
