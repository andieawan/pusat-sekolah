@extends('layouts.app')

@section('judul', ($rec->exists ? 'Ubah ' : 'Tambah ').$d['judul'])

@section('isi')
    <div class="kepala">
        <div>
            <a href="{{ route('admin.master.index', $sumber) }}">‹ {{ $d['judul'] }}</a>
            <h1>{{ $rec->exists ? 'Ubah' : 'Tambah' }} {{ mb_strtolower($d['judul']) }}</h1>
            @if ($rec->exists && isset($d['kunci']))<p class="samar">{{ $rec->{$d['kunci']} }}@if ($rec->nama ?? null) · {{ $rec->nama }}@endif</p>@endif
        </div>
    </div>

    <div class="kartu">
        <form class="form" method="post"
              action="{{ $rec->exists ? route('admin.master.update', [$sumber, $rec->id]) : route('admin.master.store', $sumber) }}">
            @csrf
            @if ($rec->exists) @method('PUT') @endif

            <div class="kisi-form">
            @foreach ($d['field'] as $f)
                @php
                    [$nama, $label, $tipe] = $f;
                    $nilai = old($nama, $rec->{$nama});
                    if ($nilai instanceof \DateTimeInterface) { $nilai = $nilai->format('Y-m-d'); }
                    $aturan = $f[3] ?? '';
                    $wajib = is_string($aturan) ? str_contains($aturan, 'required') : in_array('required', (array) $aturan, true);
                    $penuh = $tipe === 'checkbox' || in_array($nama, ['nama', 'alamat'], true);
                @endphp
                <div @class(['baris', 'penuh' => $penuh])>
                    @if ($tipe === 'checkbox')
                        @php
                            $tercentang = session()->hasOldInput()
                                ? (bool) old($nama)
                                : ($rec->exists ? (bool) $rec->{$nama} : $sumber !== 'tahun-ajaran');
                        @endphp
                        <label class="centang">
                            <input type="checkbox" name="{{ $nama }}" value="1" @checked($tercentang)>
                            {{ $label }}
                        </label>
                    @elseif ($tipe === 'select')
                        <label for="f-{{ $nama }}">{{ $label }}@if ($wajib) <span aria-hidden="true">*</span>@endif</label>
                        <select id="f-{{ $nama }}" name="{{ $nama }}" @required($wajib)>
                            <option value="">— pilih —</option>
                            @foreach (($f[4])() as $kunci => $teks)
                                <option value="{{ $kunci }}" @selected((string) $nilai === (string) $kunci)>{{ $teks }}</option>
                            @endforeach
                        </select>
                    @else
                        <label for="f-{{ $nama }}">{{ $label }}@if ($wajib) <span aria-hidden="true">*</span>@endif</label>
                        <input type="{{ $tipe === 'date' ? 'date' : 'text' }}" id="f-{{ $nama }}" name="{{ $nama }}" value="{{ $nilai }}" @required($wajib)
                               @error($nama) aria-invalid="true" @enderror>
                    @endif
                    @error($nama) <div class="galat-input">{{ $message }}</div> @enderror
                </div>
            @endforeach
            </div>

            <div class="aksi">
                <button class="tombol" type="submit">Simpan</button>
                <a class="tombol sekunder" href="{{ route('admin.master.index', $sumber) }}">Batal</a>
            </div>
            <p class="samar">Isian bertanda * wajib diisi.</p>
        </form>
    </div>

    @if ($rec->exists)
        <div class="kartu zona-bahaya">
            <h2>Hapus {{ mb_strtolower($d['judul']) }}</h2>
            <p class="samar">Data dihapus dari Pusat dan semua aplikasi diberi tahu.@if ($sumber === 'siswa') Untuk siswa yang lulus atau pindah, cukup ubah <strong>Status</strong> — riwayatnya tetap tersimpan.@endif</p>
            <form method="post" action="{{ route('admin.master.destroy', [$sumber, $rec->id]) }}" data-konfirmasi="Hapus data ini? Aplikasi lain akan diberi tahu.">
                @csrf
                @method('DELETE')
                <button class="tombol bahaya" type="submit">Hapus</button>
            </form>
        </div>
    @endif
@endsection
