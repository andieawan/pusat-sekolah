@extends('layouts.app')

@section('judul', 'Pratinjau impor')

@section('isi')
    <div class="kepala"><h1>Pratinjau impor {{ ['siswa' => 'siswa', 'kelas' => 'kelas'][$sumber] ?? 'guru & staf' }}</h1></div>

    <div class="grid">
        <div class="kartu"><div class="angka">{{ $ringkas['baru'] }}</div><div class="samar">data baru</div></div>
        <div class="kartu"><div class="angka">{{ $ringkas['ubah'] }}</div><div class="samar">akan diperbarui</div></div>
        <div class="kartu"><div class="angka">{{ $ringkas['sama'] }}</div><div class="samar">tanpa perubahan</div></div>
        <div class="kartu"><div class="angka">{{ $jumlahGalat }}</div><div class="samar">galat (dilewati)</div></div>
    </div>

    @if ($jumlahGalat > 0)
        <div class="kartu">
            <h2>Galat{{ $jumlahGalat > count($galat) ? ' (100 pertama dari '.$jumlahGalat.')' : '' }}</h2>
            <div class="gulir">
                <table class="tabel">
                    <thead><tr><th>Baris</th><th>Masalah</th></tr></thead>
                    <tbody>
                    @foreach ($galat as $g)
                        <tr><td>{{ $g['baris'] }}</td><td>{{ $g['pesan'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="samar">Baris bermasalah tidak diimpor; baris lain tetap diproses. Perbaiki file lalu unggah ulang jika ingin semuanya masuk.</p>
        </div>
    @endif

    @if ($contoh)
        <div class="kartu">
            <h2>Contoh baris yang akan diproses</h2>
            <div class="gulir">
                <table class="tabel">
                    <thead><tr><th>Baris</th><th>Aksi</th><th>{{ ['siswa' => 'NIS', 'kelas' => 'Tingkat'][$sumber] ?? 'NIP' }}</th><th>Nama</th></tr></thead>
                    <tbody>
                    @foreach ($contoh as $c)
                        <tr>
                            <td>{{ $c['baris'] }}</td>
                            <td><span class="lencana {{ $c['aksi'] === 'baru' ? 'ok' : ($c['aksi'] === 'ubah' ? 'warn' : '') }}">{{ $c['aksi'] }}</span></td>
                            <td>{{ $c['data'][['siswa' => 'nis', 'kelas' => 'tingkat'][$sumber] ?? 'nip'] }}</td>
                            <td>{{ $c['data']['nama'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="aksi">
        @if ($ringkas['baru'] + $ringkas['ubah'] > 0)
            <form method="post" action="{{ route('admin.impor.terapkan') }}">
                @csrf
                <input type="hidden" name="token" value="{{ session('impor.token') }}">
                <button class="tombol" type="submit">Terapkan ({{ $ringkas['baru'] + $ringkas['ubah'] }} baris)</button>
            </form>
        @else
            <span class="samar">Tidak ada perubahan untuk diterapkan.</span>
        @endif
        <a class="tombol sekunder" href="{{ route('admin.impor.form') }}">Batal</a>
    </div>
@endsection
