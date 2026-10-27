@extends('layouts.app')

@section('judul', 'Akun siswa')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Buat akun siswa sekaligus</h1>
            <p class="samar">Untuk siswa aktif yang belum punya akun (mis. siswa yang akan PKL). Username = NIS; password awal acak dan wajib
                diganti saat pertama masuk. Akun otomatis nonaktif bila siswa lulus, pindah, atau keluar.</p>
        </div>
        <a class="tombol sekunder" href="{{ route('admin.akun.index') }}">Kembali ke Akun</a>
    </div>

    @if (is_array($hasil) && $hasil)
        <div class="kartu cetak-saja">
            <h2>Akun siswa baru — {{ config('app.name') }}</h2>
            <p class="samar">Masuk di {{ url('/login') }} lalu ganti password. Daftar ini hanya tampil sekali.</p>
            @foreach (collect($hasil)->groupBy('kelas') as $namaKelas => $baris)
                <h3 style="margin:14px 0 6px">Kelas {{ $namaKelas ?: '-' }}</h3>
                <div class="gulir">
                    <table class="tabel">
                        <thead><tr><th>NIS</th><th>Nama</th><th>Username</th><th>Password awal</th></tr></thead>
                        <tbody>
                        @foreach ($baris as $h)
                            <tr><td class="mono">{{ $h['nis'] }}</td><td>{{ $h['nama'] }}</td><td class="mono">{{ $h['username'] }}</td><td class="mono"><strong>{{ $h['password'] }}</strong></td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
            <div class="aksi"><button type="button" class="tombol" data-aksi="cetak">Cetak daftar</button></div>
        </div>
    @endif

    <div class="kartu">
        <h2>1. Pilih kelas</h2>
        <form class="form" method="get" action="{{ route('admin.akun.siswa') }}">
            <div class="gulir">
                <table class="tabel">
                    <thead><tr><th></th><th>Kelas</th><th>Siswa belum punya akun</th></tr></thead>
                    <tbody>
                    @forelse ($kelas as $k)
                        <tr>
                            <td><input type="checkbox" name="kelas[]" value="{{ $k->id }}" @checked(in_array($k->id, $dipilih, true)) @disabled($k->tanpa_akun === 0) aria-label="Pilih {{ $k->nama }}"></td>
                            <td>{{ $k->nama }}</td>
                            <td>{{ $k->tanpa_akun ?: '— semua sudah punya akun' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="samar">Belum ada kelas di tahun ajaran aktif.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="aksi"><button class="tombol sekunder" type="submit">Tampilkan siswa</button></div>
        </form>
    </div>

    @if ($siswa->isNotEmpty())
        <form method="post" action="{{ route('admin.akun.siswa.buat') }}" data-konfirmasi="Buat akun untuk siswa yang dicentang?">
            @csrf
            <div class="kartu">
                <h2>2. Siswa ({{ $siswa->count() }})</h2>
                <div class="gulir">
                    <table class="tabel">
                        <thead><tr><th></th><th>Kelas</th><th>NIS</th><th>Nama</th><th>Username</th></tr></thead>
                        <tbody>
                        @foreach ($siswa as $s)
                            <tr>
                                <td><input type="checkbox" name="siswa[]" value="{{ $s->id }}" checked aria-label="Buat akun {{ $s->nama }}"></td>
                                <td>{{ $s->kelas?->nama }}</td>
                                <td class="mono">{{ $s->nis }}</td>
                                <td>{{ $s->nama }}</td>
                                <td><input class="mono" type="text" name="username[{{ $s->id }}]" value="{{ old('username.'.$s->id, $usulan[$s->id]) }}" maxlength="60" pattern="[A-Za-z0-9._\-]{3,60}" aria-label="Username {{ $s->nama }}" style="max-width:200px"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="kartu">
                <h2>3. Peran awal di tiap aplikasi</h2>
                <div class="gulir">
                    <table class="tabel">
                        <thead><tr><th>Aplikasi</th><th>Peran siswa</th></tr></thead>
                        <tbody>
                        @foreach ($aplikasi as $a)
                            @php($pilih = old('peran.'.$a->id, $bawaan[$a->kode] ?? ''))
                            <tr>
                                <td><strong>{{ $a->nama }}</strong> <span class="samar">{{ $a->kode }}</span></td>
                                <td>
                                    <select name="peran[{{ $a->id }}]" aria-label="Peran siswa di {{ $a->nama }}">
                                        <option value="">— tanpa peran —</option>
                                        @foreach ($a->peran_tersedia ?? [] as $r)
                                            <option value="{{ $r }}" @selected($pilih === $r)>{{ $r }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="aksi"><button class="tombol" type="submit">Buat akun siswa</button></div>
        </form>
    @elseif ($dipilih)
        <div class="kartu"><p class="samar">Semua siswa aktif di kelas terpilih sudah punya akun.</p></div>
    @endif
@endsection
