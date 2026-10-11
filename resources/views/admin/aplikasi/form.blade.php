@extends('layouts.app')

@section('judul', $baru ? 'Daftarkan aplikasi' : 'Ubah aplikasi')

@section('isi')
    <div class="kepala"><h1>{{ $baru ? 'Daftarkan aplikasi' : 'Ubah '.$app->nama }}</h1></div>

    @if ($baru)
        <div class="notif info">
            Isi cepat:
            @foreach (array_keys(config('pusat.aplikasi_bawaan')) as $kode)
                <a href="{{ route('admin.aplikasi.create', ['kode' => $kode]) }}">{{ $kode }}</a>@unless ($loop->last) · @endunless
            @endforeach
        </div>
    @endif

    @php
        $adaOld = session()->hasOldInput();
        $scopeDipilih = $adaOld ? (array) old('scope', []) : ($app->scope ?? []);
    @endphp

    <form class="form" method="post" action="{{ $baru ? route('admin.aplikasi.store') : route('admin.aplikasi.update', $app) }}">
        @csrf
        @unless ($baru) @method('PUT') @endunless

        <div class="kartu">
            <h2>Identitas</h2>
            <div class="kolom2">
                <div class="baris">
                    <label for="kode">Kode</label>
                    <input type="text" id="kode" name="kode" value="{{ old('kode', $app->kode) }}" required
                           {{ $baru ? '' : 'readonly' }} placeholder="absensi" autocapitalize="none">
                    <div class="samar">Huruf kecil/angka/_/-. Tidak bisa diubah setelah dibuat.</div>
                </div>
                <div class="baris">
                    <label for="nama">Nama</label>
                    <input type="text" id="nama" name="nama" value="{{ old('nama', $app->nama) }}" required>
                </div>
            </div>
            <div class="baris">
                <label for="base_url">Alamat aplikasi (URL)</label>
                <input type="url" id="base_url" name="base_url" value="{{ old('base_url', $app->base_url) }}" required placeholder="https://absensi.smkibupakusari.sch.id">
            </div>
            <label class="centang">
                <input type="checkbox" name="aktif" value="1" @checked($adaOld ? (bool) old('aktif') : (bool) $app->aktif)>
                Aplikasi aktif
            </label>
        </div>

        <div class="kartu">
            <h2>Login tunggal (SSO)</h2>
            <div class="baris">
                <label for="redirect_uris">Redirect URI (satu per baris)</label>
                <textarea id="redirect_uris" name="redirect_uris" required class="mono" placeholder="https://absensi.smkibupakusari.sch.id/sso/callback">{{ old('redirect_uris', implode("\n", $app->redirect_uris ?? [])) }}</textarea>
                <div class="samar">Harus persis sama dengan yang dikirim aplikasi. Alamat lain ditolak (mencegah pembajakan login).</div>
            </div>
            <div class="baris">
                <label for="peran_tersedia">Peran yang tersedia (pisahkan dengan koma)</label>
                <textarea id="peran_tersedia" name="peran_tersedia" required>{{ old('peran_tersedia', implode(', ', $app->peran_tersedia ?? [])) }}</textarea>
            </div>
        </div>

        <div class="kartu">
            <h2>Hak baca data (scope)</h2>
            <p class="samar">Beri sekecil mungkin. Varian "data dasar" tidak memuat nomor HP dan tanggal lahir.</p>
            @foreach (config('pusat.scope') as $kunci => $label)
                <label class="centang">
                    <input type="checkbox" name="scope[]" value="{{ $kunci }}" @checked(in_array($kunci, $scopeDipilih, true))>
                    {{ $label }} <span class="samar mono">{{ $kunci }}</span>
                </label>
            @endforeach
        </div>

        <div class="kartu">
            <h2>Webhook</h2>
            <div class="baris">
                <label for="webhook_url">URL penerima webhook (opsional)</label>
                <input type="url" id="webhook_url" name="webhook_url" value="{{ old('webhook_url', $app->webhook_url) }}" placeholder="https://absensi.smkibupakusari.sch.id/pusat/webhook">
                <div class="samar">Perubahan data induk dikirim ke sini, ditandatangani HMAC. Boleh http:// untuk jaringan lokal.</div>
            </div>
        </div>

        <div class="aksi">
            <button class="tombol" type="submit">{{ $baru ? 'Daftarkan' : 'Simpan' }}</button>
            <a class="tombol sekunder" href="{{ route('admin.aplikasi.index') }}">Batal</a>
        </div>
    </form>

    @unless ($baru)
        <div class="kartu">
            <h2>Kredensial</h2>
            <div class="baris">
                <label for="client_id">Client ID</label>
                <div class="baris-input">
                    <input type="text" id="client_id" class="mono" value="{{ $app->client_id }}" readonly>
                    <button class="tombol sekunder kecil" type="button" data-aksi="salin" data-target="#client_id">Salin</button>
                </div>
            </div>
            <div class="baris">
                <label for="wh_secret">Rahasia webhook</label>
                <div class="baris-input">
                    <input type="password" id="wh_secret" class="mono" value="{{ $app->webhook_secret }}" readonly>
                    <button class="tombol sekunder kecil" type="button" data-aksi="tampilkan" data-target="#wh_secret">Tampilkan</button>
                    <button class="tombol sekunder kecil" type="button" data-aksi="salin" data-target="#wh_secret">Salin</button>
                </div>
            </div>
            <p class="samar">Client secret hanya disimpan sebagai hash dan tidak bisa ditampilkan lagi. Dibuat {{ $app->secret_dibuat_pada?->diffForHumans() ?? '—' }}.</p>
            <div class="aksi">
                <form method="post" action="{{ route('admin.aplikasi.rotasi', $app) }}" data-konfirmasi="Buat client secret baru? Secret lama langsung tidak berlaku dan aplikasi harus diperbarui.">
                    @csrf
                    <input type="hidden" name="jenis" value="klien">
                    <button class="tombol bahaya kecil" type="submit">Putar client secret</button>
                </form>
                <form method="post" action="{{ route('admin.aplikasi.rotasi', $app) }}" data-konfirmasi="Buat rahasia webhook baru? Penerima harus memakai rahasia baru agar tanda tangan cocok.">
                    @csrf
                    <input type="hidden" name="jenis" value="webhook">
                    <button class="tombol bahaya kecil" type="submit">Putar rahasia webhook</button>
                </form>
                <form method="post" action="{{ route('admin.aplikasi.destroy', $app) }}" data-konfirmasi="Hapus aplikasi ini beserta semua aksesnya?">
                    @csrf
                    @method('DELETE')
                    <button class="tombol bahaya kecil" type="submit">Hapus aplikasi</button>
                </form>
            </div>
        </div>
    @endunless
@endsection
