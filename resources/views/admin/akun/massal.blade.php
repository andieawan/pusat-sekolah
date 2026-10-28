@extends('layouts.app')

@section('judul', 'Akun guru & staf')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Buat akun guru &amp; staf sekaligus</h1>
            <p class="samar">Untuk guru/staf aktif (menu Guru &amp; Staf) yang belum punya akun. Username = NIP; password awal acak dan
                wajib diganti saat pertama masuk. Akun langsung tersambung ke data guru/staf-nya.</p>
        </div>
        <a class="tombol sekunder" href="{{ route('admin.akun.index') }}">Kembali ke Akun</a>
    </div>

    @if (is_array($hasil) && $hasil)
        <div class="kartu cetak-saja">
            <h2>Daftar akun baru — {{ config('app.name') }}</h2>
            <p class="samar">Bagikan ke masing-masing guru/staf. Masuk di {{ url('/login') }}, lalu ganti password. Daftar ini hanya tampil sekali.</p>
            <div class="gulir">
                <table class="tabel">
                    <thead><tr><th>Nama</th><th>Jenis</th><th>Username</th><th>Password awal</th></tr></thead>
                    <tbody>
                    @foreach ($hasil as $h)
                        <tr><td>{{ $h['nama'] }}</td><td>{{ $h['jenis'] === 'staf' ? 'Staf' : 'Guru' }}</td><td class="mono">{{ $h['username'] }}</td><td class="mono"><strong>{{ $h['password'] }}</strong></td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="aksi"><button type="button" class="tombol" data-aksi="cetak">Cetak daftar</button></div>
        </div>
    @endif

    @if ($pegawai->isEmpty())
        <div class="kartu"><p class="samar">Semua guru &amp; staf aktif sudah punya akun. Tambahkan dulu data di menu <a href="{{ route('admin.master.index', 'pegawai') }}">Guru &amp; Staf</a> bila ada yang belum terdaftar.</p></div>
    @else
        <form method="post" action="{{ route('admin.akun.massal.buat') }}" data-konfirmasi="Buat akun untuk guru/staf yang dicentang?">
            @csrf
            <div class="kartu">
                <h2>1. Pilih guru &amp; staf ({{ $pegawai->count() }} belum punya akun)</h2>
                <p class="samar">Guru yang sudah punya akun di aplikasi Absensi: samakan username di sini dengan username Absensi-nya,
                    agar akun lamanya (mapel &amp; kelas yang diajar) langsung tersambung saat masuk lewat Pusat.</p>
                <div class="gulir">
                    <table class="tabel">
                        <thead><tr><th></th><th>Nama</th><th>Jenis</th><th>NIP</th><th>Username</th></tr></thead>
                        <tbody>
                        @foreach ($pegawai as $p)
                            <tr>
                                <td><input type="checkbox" name="pegawai[]" value="{{ $p->id }}" checked aria-label="Buat akun {{ $p->nama }}"></td>
                                <td>{{ $p->nama }}</td>
                                <td>{{ $p->jenis === 'staf' ? 'Staf' : 'Guru' }}</td>
                                <td class="mono">{{ $p->nip }}</td>
                                <td><input type="text" name="username[{{ $p->id }}]" value="{{ old('username.'.$p->id, $usulan[$p->id]) }}" maxlength="60" pattern="[A-Za-z0-9._\-]{3,60}" aria-label="Username {{ $p->nama }}" class="mono input-sedang"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="kartu">
                <h2>2. Peran awal di tiap aplikasi</h2>
                <p class="samar">Bisa diubah per orang nanti di menu Akun. Pilih "— tanpa peran —" bila aksesnya diatur manual.</p>
                <div class="gulir">
                    <table class="tabel">
                        <thead><tr><th>Aplikasi</th><th>Untuk guru</th><th>Untuk staf</th></tr></thead>
                        <tbody>
                        @foreach ($aplikasi as $a)
                            <tr>
                                <td><strong>{{ $a->nama }}</strong> <span class="samar">{{ $a->kode }}</span></td>
                                @foreach (['guru', 'staf'] as $j)
                                    @php($pilih = old("peran.{$a->id}.{$j}", $bawaan[$a->kode][$j] ?? ''))
                                    <td>
                                        <select name="peran[{{ $a->id }}][{{ $j }}]" aria-label="Peran {{ $j }} di {{ $a->nama }}">
                                            <option value="">— tanpa peran —</option>
                                            @foreach ($a->peran_tersedia ?? [] as $r)
                                                <option value="{{ $r }}" @selected($pilih === $r)>{{ $r }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="aksi"><button class="tombol" type="submit">Buat akun</button></div>
        </form>
    @endif
@endsection
