@extends('layouts.app')

@section('judul', 'Profil Sekolah')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Profil sekolah</h1>
            <p class="samar">Satu tempat untuk identitas sekolah. Kop surat, nama &amp; NIP kepala sekolah, dan logo di Absensi, BK, TU, PKL,
                dan Website otomatis mengikuti isian ini.</p>
        </div>
    </div>

    <form class="form" method="post" action="{{ route('admin.profil.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="kartu">
            <h2>Identitas &amp; kop surat</h2>
            @foreach ($kolom as $k => [$label, $aturan, $contoh])
                <div class="baris">
                    <label for="{{ $k }}">{{ $label }}</label>
                    <input id="{{ $k }}" name="{{ $k }}" type="{{ $k === 'email' ? 'email' : 'text' }}" value="{{ old($k, $nilai[$k] ?? '') }}" placeholder="{{ $contoh }}" @required(str_starts_with($aturan, 'required'))>
                    @error($k) <div class="galat-input">{{ $message }}</div> @enderror
                </div>
            @endforeach
            <div class="baris">
                <label for="logo">Logo sekolah (PNG/JPG, maks. 2 MB)</label>
                @if ($logo)<div><img src="{{ route('logo.sekolah') }}?v={{ $nilai['logo_versi'] ?? '' }}" alt="Logo sekolah" width="72" height="72" class="logo-pratinjau"></div>@endif
                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp">
                @if ($logo)<label class="centang"><input type="checkbox" name="hapus_logo" value="1"> Hapus logo</label>@endif
                @error('logo') <div class="galat-input">{{ $message }}</div> @enderror
            </div>
            <div class="baris">
                <label>Guru BK (untuk tanda tangan surat BK)</label>
                <div>{{ $guruBk ? implode(', ', $guruBk) : '—' }}</div>
                <div class="samar">Otomatis: akun yang diberi peran <strong>bk</strong> / <strong>guru_bk</strong> di menu Akun.</div>
            </div>
        </div>

        <div class="kartu">
            <h2>Aturan password akun</h2>
            <p class="samar">Berlaku untuk semua akun Pusat (dan karena itu untuk semua aplikasi yang masuk lewat Pusat).</p>
            <div class="baris">
                <label for="pw_min">Panjang minimal</label>
                <input id="pw_min" name="pw_min" type="number" min="8" max="64" value="{{ old('pw_min', $pw['min']) }}" required class="input-pendek">
            </div>
            <label class="centang"><input type="checkbox" name="pw_huruf" value="1" @checked($pw['huruf'])> Wajib ada huruf</label>
            <label class="centang"><input type="checkbox" name="pw_angka" value="1" @checked($pw['angka'])> Wajib ada angka</label>
            <label class="centang"><input type="checkbox" name="pw_simbol" value="1" @checked($pw['simbol'])> Wajib ada simbol</label>
        </div>

        <div class="aksi"><button class="tombol" type="submit">Simpan &amp; kabarkan ke semua aplikasi</button></div>
    </form>
@endsection
