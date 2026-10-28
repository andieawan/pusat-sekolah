@extends('layouts.app')

@section('judul', 'Pengampu Mapel')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Pengampu mapel</h1>
            <p class="samar">Siapa mengajar mapel apa di kelas mana{{ $ta ? ' — tahun ajaran '.$ta->nama : '' }}. Absensi memakai daftar ini
                untuk menentukan kelas &amp; mapel yang boleh diisi tiap guru. Setelah kenaikan kelas, isi lagi untuk kelas tahun ajaran baru.</p>
        </div>
    </div>

    @if (session('rincian'))
        <div class="notif galat" role="alert">
            <ul>@foreach (session('rincian') as $r)<li>{{ $r }}</li>@endforeach</ul>
        </div>
    @endif

    @if (! $ta)
        <div class="kartu"><p class="samar">Belum ada tahun ajaran aktif. Atur dulu di <a href="{{ route('admin.master.index', 'tahun-ajaran') }}">Tahun Ajaran</a>.</p></div>
    @elseif ($guru->isEmpty() || $mapel->isEmpty() || $kelas->isEmpty())
        <div class="kartu"><p class="samar">Isi dulu
            @if ($guru->isEmpty())<a href="{{ route('admin.master.index', 'pegawai') }}">Guru &amp; Staf</a> (jenis guru)@endif
            @if ($mapel->isEmpty()) · <a href="{{ route('admin.master.index', 'mapel') }}">Mata Pelajaran</a>@endif
            @if ($kelas->isEmpty()) · <a href="{{ route('admin.master.index', 'kelas') }}">Kelas</a> tahun ajaran {{ $ta->nama }}@endif
            .</p></div>
    @else
        <div class="kartu">
            <h2>Tambah pengampu</h2>
            <form class="form" method="post" action="{{ route('admin.pengampu.store') }}">
                @csrf
                <div class="kisi-form">
                    <div class="baris"><label for="pegawai_id">Guru</label>
                        <select id="pegawai_id" name="pegawai_id" required>
                            <option value="">— pilih guru —</option>
                            @foreach ($guru as $g)<option value="{{ $g->id }}" @selected((string) old('pegawai_id', request('pegawai_id')) === (string) $g->id)>{{ $g->nama }}</option>@endforeach
                        </select></div>
                    <div class="baris"><label for="mapel_id">Mata pelajaran</label>
                        <select id="mapel_id" name="mapel_id" required>
                            <option value="">— pilih mapel —</option>
                            @foreach ($mapel as $m)<option value="{{ $m->id }}" @selected((string) old('mapel_id') === (string) $m->id)>{{ $m->nama }} ({{ $m->kode }})</option>@endforeach
                        </select></div>
                </div>
                <fieldset class="pilih-kelas">
                    <legend>Kelas (boleh lebih dari satu)</legend>
                    <div class="kisi-centang">
                        @foreach ($kelas as $k)
                            <label class="centang"><input type="checkbox" name="kelas[]" value="{{ $k->id }}" @checked(in_array($k->id, old('kelas', []))) > {{ $k->nama }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="aksi"><button class="tombol" type="submit">Simpan pengampu</button></div>
            </form>
        </div>

        <details class="kartu lipat" @if (session('rincian')) open @endif>
            <summary>Impor dari CSV (banyak sekaligus)</summary>
            <p class="samar">Kolom: <span class="mono">guru;mapel;kelas</span>. Guru = NIP, username, atau nama; mapel = kode atau nama; kelas boleh banyak dipisah koma.
                File dari Absensi (<em>Pasangan Mapel → Unduh CSV untuk Pusat</em>) bisa langsung diimpor.</p>
            <form class="form" method="post" action="{{ route('admin.pengampu.impor') }}" enctype="multipart/form-data">
                @csrf
                <div class="baris"><div><label for="berkas">Berkas CSV</label><input id="berkas" type="file" name="berkas" accept=".csv,text/csv" required></div></div>
                <div class="aksi"><button class="tombol sekunder" type="submit">Impor</button> <a href="{{ route('admin.pengampu.templat') }}">Unduh templat</a></div>
            </form>
        </details>

        <form class="pencarian" method="get" action="{{ route('admin.pengampu.index') }}" role="search">
            <select name="pegawai_id" aria-label="Saring guru"><option value="">Semua guru</option>
                @foreach ($guru as $g)<option value="{{ $g->id }}" @selected((string) request('pegawai_id') === (string) $g->id)>{{ $g->nama }}</option>@endforeach</select>
            <select name="kelas_id" aria-label="Saring kelas"><option value="">Semua kelas</option>
                @foreach ($kelas as $k)<option value="{{ $k->id }}" @selected((string) request('kelas_id') === (string) $k->id)>{{ $k->nama }}</option>@endforeach</select>
            <button class="tombol sekunder" type="submit">Terapkan</button>
            @if (request()->filled('pegawai_id') || request()->filled('kelas_id'))<a href="{{ route('admin.pengampu.index') }}">Hapus saringan</a>@endif
        </form>

        <div class="kartu">
            <p class="jumlah">{{ $total }} pengampu</p>
            @if ($kelasKosong->isNotEmpty() && ! request()->filled('pegawai_id'))
                <p class="samar">Kelas belum punya pengampu: {{ $kelasKosong->implode(', ') }}</p>
            @endif
            <div class="gulir">
                <table class="tabel hp-kartu">
                    <thead><tr><th>Guru</th><th>Mata pelajaran</th><th>Kelas</th></tr></thead>
                    <tbody>
                    @forelse ($daftar as $grup)
                        @php($p0 = $grup->first())
                        <tr>
                            <td>{{ $p0->pegawai?->nama ?? '—' }}</td>
                            <td data-label="Mapel">{{ $p0->mapel?->nama ?? '—' }}</td>
                            <td data-label="Kelas">
                                <div class="daftar-lencana">
                                    @foreach ($grup as $p)
                                        <form class="sebaris" method="post" action="{{ route('admin.pengampu.destroy', $p->id) }}" data-konfirmasi="Hapus {{ $p0->mapel?->nama }} di {{ $p->kelas?->nama }} dari {{ $p0->pegawai?->nama }}?">
                                            @csrf @method('DELETE')
                                            <span class="lencana">{{ $p->kelas?->nama }}
                                                <button type="submit" class="lencana-hapus" aria-label="Hapus {{ $p->kelas?->nama }}">×</button></span>
                                        </form>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="kosong">Belum ada pengampu{{ request()->filled('pegawai_id') || request()->filled('kelas_id') ? ' yang cocok' : '' }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
