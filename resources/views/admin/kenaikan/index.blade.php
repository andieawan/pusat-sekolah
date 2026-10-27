@extends('layouts.app')

@section('judul', 'Kenaikan Kelas')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Kenaikan kelas</h1>
            <p class="samar">Dilakukan sekali di akhir tahun ajaran. Siswa aktif dipindah ke kelas tahun ajaran berikutnya
                (X → XI → XII), kelas XII diluluskan. Hasilnya otomatis terkirim ke semua aplikasi (Absensi, BK, TU, PKL).</p>
        </div>
    </div>

    <div class="kartu">
        <h2>1. Tahun ajaran</h2>
        <form class="form" method="get" action="{{ route('admin.kenaikan.index') }}">
            <div class="baris">
                <div><label for="dari">Dari</label>
                    <select id="dari" name="dari">@foreach ($semua as $t)<option value="{{ $t->id }}" @selected($dari?->id === $t->id)>{{ $t->nama }}{{ $t->aktif ? ' (aktif)' : '' }}</option>@endforeach</select></div>
                <div><label for="ke">Ke</label>
                    <select id="ke" name="ke"><option value="">— pilih —</option>@foreach ($semua as $t)<option value="{{ $t->id }}" @selected($ke?->id === $t->id)>{{ $t->nama }}{{ $t->aktif ? ' (aktif)' : '' }}</option>@endforeach</select></div>
            </div>
            <div class="aksi"><button class="tombol sekunder" type="submit">Tampilkan usulan</button></div>
        </form>
        @if (! $ke)
            <form class="form" method="post" action="{{ route('admin.kenaikan.tahun') }}" style="margin-top:12px">
                @csrf
                <p class="samar">Tahun ajaran tujuan belum ada. Buat dulu (belum langsung aktif):</p>
                <div class="baris"><div><label for="nama">Nama tahun ajaran baru</label><input id="nama" name="nama" value="{{ old('nama', $usulNama) }}" placeholder="2027/2028" required></div></div>
                <div class="aksi"><button class="tombol" type="submit">Buat tahun ajaran</button></div>
            </form>
        @endif
    </div>

    @if ($dari && $ke && $dari->id !== $ke->id)
        @if ($rencana->isEmpty())
            <div class="kartu"><p class="samar">Tidak ada siswa aktif di kelas tahun ajaran {{ $dari->nama }}. Kenaikan sudah dilakukan atau data kelas/siswa belum diisi.</p></div>
        @else
        <form method="post" action="{{ route('admin.kenaikan.terapkan') }}" data-konfirmasi="Terapkan kenaikan kelas {{ $dari->nama }} → {{ $ke->nama }}? Perubahan langsung dikirim ke semua aplikasi.">
            @csrf
            <input type="hidden" name="dari" value="{{ $dari->id }}">
            <input type="hidden" name="ke" value="{{ $ke->id }}">
            <div class="kartu">
                <h2>2. Kelas tujuan</h2>
                <p class="samar">Kelas yang belum ada di {{ $ke->nama }} dibuat otomatis (jurusan ikut kelas asal, wali kelas diatur sesudahnya).</p>
                <div class="gulir">
                    <table class="tabel">
                        <thead><tr><th>Kelas {{ $dari->nama }}</th><th>Siswa aktif</th><th>Naik ke ({{ $ke->nama }})</th></tr></thead>
                        <tbody>
                        @foreach ($rencana as $r)
                            @php($k = $r['kelas'])
                            <tr>
                                <td><strong>{{ $k->nama }}</strong> <span class="samar">{{ $k->jurusan?->kode }}</span></td>
                                <td>{{ $r['siswa']->count() }}</td>
                                <td>
                                    <select name="tujuan[{{ $k->id }}]" aria-label="Tujuan {{ $k->nama }}">
                                        @if ($r['naik'] === 'lulus')
                                            <option value="lulus" selected>Lulus</option>
                                        @endif
                                        @if (str_starts_with($r['naik'], 'baru:'))
                                            <option value="{{ $r['naik'] }}" selected>{{ $r['naikNama'] }} (kelas baru)</option>
                                        @endif
                                        @foreach ($kelasKe as $t)
                                            <option value="{{ $t->id }}" @selected($r['naik'] === (string) $t->id)>{{ $t->nama }}</option>
                                        @endforeach
                                        @if ($r['naik'] !== 'lulus')
                                            <option value="lulus">Lulus</option>
                                        @endif
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="kartu">
                <h2>3. Pengecualian per siswa</h2>
                <p class="samar">Semua siswa ikut kelasnya. Ubah hanya bagi yang tinggal kelas (tetap di tingkat yang sama, kelas bernama sama di {{ $ke->nama }}), keluar, atau lulus lebih awal.</p>
                @foreach ($rencana as $r)
                    <details style="border-top:1px solid #e5e7eb;padding:8px 0">
                        <summary style="cursor:pointer"><strong>{{ $r['kelas']->nama }}</strong> <span class="samar">— {{ $r['siswa']->count() }} siswa</span></summary>
                        <div class="gulir">
                            <table class="tabel">
                                <thead><tr><th>NIS</th><th>Nama</th><th>Keputusan</th></tr></thead>
                                <tbody>
                                @foreach ($r['siswa'] as $s)
                                    <tr>
                                        <td class="mono">{{ $s->nis }}</td>
                                        <td>{{ $s->nama }}</td>
                                        <td>
                                            <select name="siswa[{{ $s->id }}]" aria-label="Keputusan {{ $s->nama }}">
                                                <option value="naik">{{ $r['naik'] === 'lulus' ? 'Lulus' : 'Naik' }} (ikut kelas)</option>
                                                <option value="tinggal" @selected(old('siswa.'.$s->id) === 'tinggal')>Tinggal kelas</option>
                                                <option value="keluar" @selected(old('siswa.'.$s->id) === 'keluar')>Keluar</option>
                                                @if ($r['naik'] !== 'lulus')<option value="lulus" @selected(old('siswa.'.$s->id) === 'lulus')>Lulus</option>@endif
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>

            <div class="kartu">
                <h2>4. Terapkan</h2>
                <label class="centang"><input type="checkbox" name="aktifkan" value="1" checked> Jadikan {{ $ke->nama }} tahun ajaran aktif setelah kenaikan</label>
                <label class="centang"><input type="checkbox" name="yakin" value="1" required> Saya sudah memeriksa kelas tujuan dan pengecualian di atas</label>
                <div class="aksi"><button class="tombol" type="submit">Terapkan kenaikan kelas</button></div>
            </div>
        </form>
        @endif
    @endif
@endsection
