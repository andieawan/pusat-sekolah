@extends('layouts.app')

@section('judul', ($rec->exists ? 'Ubah ' : 'Tambah ').$d['judul'])

@section('isi')
    <div class="kepala">
        <h1>{{ $rec->exists ? 'Ubah' : 'Tambah' }} {{ $d['judul'] }}</h1>
    </div>

    <div class="kartu">
        <form class="form" method="post"
              action="{{ $rec->exists ? route('admin.master.update', [$sumber, $rec->id]) : route('admin.master.store', $sumber) }}">
            @csrf
            @if ($rec->exists) @method('PUT') @endif

            @foreach ($d['field'] as $f)
                @php
                    [$nama, $label, $tipe] = $f;
                    $nilai = old($nama, $rec->{$nama});
                    if ($nilai instanceof \DateTimeInterface) { $nilai = $nilai->format('Y-m-d'); }
                @endphp
                <div class="baris">
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
                        <label for="f-{{ $nama }}">{{ $label }}</label>
                        <select id="f-{{ $nama }}" name="{{ $nama }}">
                            <option value="">— pilih —</option>
                            @foreach (($f[4])() as $kunci => $teks)
                                <option value="{{ $kunci }}" @selected((string) $nilai === (string) $kunci)>{{ $teks }}</option>
                            @endforeach
                        </select>
                    @else
                        <label for="f-{{ $nama }}">{{ $label }}</label>
                        <input type="{{ $tipe === 'date' ? 'date' : 'text' }}" id="f-{{ $nama }}" name="{{ $nama }}" value="{{ $nilai }}">
                    @endif
                    @error($nama) <div class="galat-input">{{ $message }}</div> @enderror
                </div>
            @endforeach

            <div class="aksi">
                <button class="tombol" type="submit">Simpan</button>
                <a class="tombol sekunder" href="{{ route('admin.master.index', $sumber) }}">Batal</a>
            </div>
        </form>
    </div>
@endsection
