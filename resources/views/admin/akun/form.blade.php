@extends('layouts.app')

@section('judul', $akun->exists ? 'Ubah akun' : 'Tambah akun')

@section('isi')
    <div class="kepala"><h1>{{ $akun->exists ? 'Ubah akun '.$akun->username : 'Tambah akun' }}</h1></div>

    @php $adaOld = session()->hasOldInput(); @endphp

    <form class="form" method="post" action="{{ $akun->exists ? route('admin.akun.update', $akun) : route('admin.akun.store') }}">
        @csrf
        @if ($akun->exists) @method('PUT') @endif

        <div class="kartu">
            <h2>Identitas</h2>
            <div class="kolom2">
                <div class="baris">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="{{ old('username', $akun->username) }}" required
                           autocomplete="off" autocapitalize="none" spellcheck="false">
                </div>
                <div class="baris">
                    <label for="nama">Nama lengkap</label>
                    <input type="text" id="nama" name="nama" value="{{ old('nama', $akun->nama) }}" required>
                </div>
            </div>
            <div class="baris">
                <label for="email">Email (opsional)</label>
                <input type="email" id="email" name="email" value="{{ old('email', $akun->email) }}">
            </div>
            <div class="kolom2">
                <div class="baris">
                    <label for="pegawai_id">Terhubung ke guru/staf</label>
                    <select id="pegawai_id" name="pegawai_id">
                        <option value="">— tidak —</option>
                        @foreach ($pegawai as $id => $nama)
                            <option value="{{ $id }}" @selected((string) old('pegawai_id', $akun->pegawai_id) === (string) $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="baris">
                    <label for="siswa_nis">…atau ke siswa (isi NIS)</label>
                    <input type="text" id="siswa_nis" name="siswa_nis" value="{{ old('siswa_nis', $nisSiswa) }}" autocomplete="off">
                </div>
            </div>
        </div>

        <div class="kartu">
            <h2>Password</h2>
            <div class="kolom2">
                <div class="baris">
                    <label for="password">{{ $akun->exists ? 'Password baru (kosongkan jika tidak diganti)' : 'Password' }}</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" {{ $akun->exists ? '' : 'required' }}>
                </div>
                <div class="baris">
                    <label for="password_confirmation">Ulangi password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                </div>
            </div>
            <div class="aksi">
                <button class="tombol sekunder kecil" type="button" data-aksi="acak-password" data-target="#password,#password_confirmation">Buat password acak</button>
                <span class="samar">{{ \App\Support\AturanPassword::keterangan() }} Sampaikan ke pemilik akun lewat jalur aman.</span>
            </div>
        </div>

        <div class="kartu">
            <h2>Akses aplikasi</h2>
            @forelse ($aplikasi as $a)
                @php
                    $terpilih = $adaOld ? (array) old('peran.'.$a->id, []) : ($peranTerpilih[$a->id] ?? []);
                @endphp
                <div class="matriks">
                    <strong>{{ $a->nama }}</strong>
                    @unless ($a->aktif) <span class="lencana warn">nonaktif</span> @endunless
                    <div>
                        @foreach ($a->peran_tersedia as $peran)
                            <label class="centang">
                                <input type="checkbox" name="peran[{{ $a->id }}][]" value="{{ $peran }}" @checked(in_array($peran, $terpilih, true))>
                                {{ $peran }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="samar">Belum ada aplikasi terdaftar. Daftarkan di menu Aplikasi.</p>
            @endforelse
        </div>

        <div class="kartu">
            <label class="centang">
                <input type="checkbox" name="aktif" value="1" @checked($adaOld ? (bool) old('aktif') : (bool) $akun->aktif)>
                Akun aktif
            </label>
            <label class="centang">
                <input type="checkbox" name="superadmin" value="1" @checked($adaOld ? (bool) old('superadmin') : (bool) $akun->superadmin)>
                Superadmin Pusat (mengelola semua data, akun, dan aplikasi; otomatis berperan "admin" di semua aplikasi)
            </label>
        </div>

        <div class="aksi">
            <button class="tombol" type="submit">Simpan</button>
            <a class="tombol sekunder" href="{{ route('admin.akun.index') }}">Batal</a>
        </div>
    </form>
@endsection
