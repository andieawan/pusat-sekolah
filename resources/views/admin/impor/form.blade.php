@extends('layouts.app')

@section('judul', 'Impor CSV')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Impor data dari CSV</h1>
            <p class="samar">Selalu ada tahap pratinjau dulu; data baru ditulis setelah Anda menekan "Terapkan".</p>
        </div>
    </div>

    <div class="kartu">
        <form class="form" method="post" action="{{ route('admin.impor.pratinjau') }}" enctype="multipart/form-data">
            @csrf
            <div class="baris">
                <span class="label">Jenis data</span>
                <label class="centang"><input type="radio" name="sumber" value="siswa" @checked(old('sumber', 'siswa') === 'siswa')> Siswa (kunci: NIS)</label>
                <label class="centang"><input type="radio" name="sumber" value="pegawai" @checked(old('sumber') === 'pegawai')> Guru &amp; staf (kunci: NIP/ID)</label>
                <label class="centang"><input type="radio" name="sumber" value="kelas" @checked(old('sumber') === 'kelas')> Kelas (kunci: nama + tahun ajaran)</label>
                <label class="centang"><input type="radio" name="sumber" value="mapel" @checked(old('sumber') === 'mapel')> Mata pelajaran (kunci: kode)</label>
            </div>
            <div class="baris">
                <label for="berkas">File CSV (maks. 2 MB / 5.000 baris)</label>
                <input type="file" id="berkas" name="berkas" accept=".csv,.txt,text/csv" required>
            </div>
            <button class="tombol" type="submit">Pratinjau</button>
        </form>
    </div>

    <div class="kartu">
        <h2>Format file</h2>
        <p class="samar">Baris pertama adalah judul kolom. Pemisah <code>,</code> atau <code>;</code> dikenali otomatis (Excel Indonesia: Simpan sebagai "CSV (pemisah koma)"). Kolom yang tidak ada dibiarkan kosong.</p>
        @foreach ($kolom as $sumber => $daftar)
            <p>
                <strong>{{ ['siswa' => 'Siswa', 'kelas' => 'Kelas', 'mapel' => 'Mata pelajaran'][$sumber] ?? 'Guru & staf' }}</strong>
                — <a href="{{ route('admin.impor.templat', $sumber) }}">unduh templat</a><br>
                <span class="mono">{{ implode('; ', $daftar) }}</span>
            </p>
        @endforeach
        <ul class="samar">
            <li>Urutan impor: <strong>Guru &amp; staf → Kelas → Siswa</strong>. Di file kelas: <code>jurusan</code> diisi kode jurusan, <code>tahun_ajaran</code> kosong = tahun aktif, <code>wali</code> diisi NIP/ID guru.</li>
            <li>Kolom <code>kelas</code> diisi nama kelas persis seperti di menu Kelas pada tahun ajaran aktif.</li>
            <li><code>jenis_kelamin</code>: L/P. <code>status</code>: aktif, lulus, pindah, keluar. <code>tanggal_lahir</code>: YYYY-MM-DD atau DD/MM/YYYY.</li>
            <li>Mengimpor file yang sama dua kali aman: data dengan NIS/NIP sama diperbarui, bukan digandakan.</li>
            <li>Siswa yang statusnya diubah menjadi lulus/pindah/keluar otomatis menonaktifkan akun loginnya.</li>
        </ul>
    </div>
@endsection
