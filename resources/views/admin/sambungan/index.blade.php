@extends('layouts.app')

@section('judul', 'Sambungan')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Sambungan antar-aplikasi</h1>
            <p class="samar">Cukup klik <strong>Sambungkan</strong>. Pusat membuat kunci rahasia sendiri lalu mengirimkannya ke kedua aplikasi —
                tidak perlu mengisi <code>.env</code> atau menyalin kunci. Setelah tersambung, cek di halaman <em>Integrasi</em> aplikasi yang bersangkutan
                dan klik <em>Uji koneksi</em>.</p>
        </div>
    </div>

    @foreach ($daftar as $jenis => $s)
        @php
            $def = $s['def'];
            $row = $s['row'];
            $nyala = $row?->aktif;
            $kurang = collect(['pemberi' => $s['pemberi'], 'peminta' => $s['peminta']])->filter(fn ($a) => ! $a)->keys();
        @endphp
        <div class="kartu">
            <div class="kepala" style="margin-bottom:6px">
                <div>
                    <h2 style="margin:0">{{ $def['nama'] }}
                        @if ($nyala)<span class="lencana ok">tersambung</span>@else<span class="lencana">belum tersambung</span>@endif
                    </h2>
                    <p class="samar" style="margin:4px 0 0">{{ $def['keterangan'] }}</p>
                </div>
                <div class="aksi">
                    @if ($kurang->isNotEmpty())
                        <span class="samar">Aplikasi
                            @foreach ($kurang as $k)"{{ $def[$k] }}"@if (! $loop->last) & @endif @endforeach
                            belum terdaftar di menu <a href="{{ route('admin.aplikasi.index') }}">Aplikasi</a>.</span>
                    @elseif (! $nyala)
                        <form method="post" action="{{ route('admin.sambungan.nyalakan', $jenis) }}">
                            @csrf
                            <button class="tombol" type="submit">Sambungkan</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.sambungan.kabari', $jenis) }}">
                            @csrf
                            <button class="tombol sekunder kecil" type="submit">Kirim ulang</button>
                        </form>
                        @if ($def['kunci'])
                            <form method="post" action="{{ route('admin.sambungan.kunci', $jenis) }}" data-konfirmasi="Buat kunci baru? Kunci lama langsung tidak berlaku.">
                                @csrf
                                <button class="tombol sekunder kecil" type="submit">Ganti kunci</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('admin.sambungan.matikan', $jenis) }}" data-konfirmasi="Putuskan sambungan {{ $def['nama'] }}?">
                            @csrf
                            <button class="tombol bahaya kecil" type="submit">Putuskan</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="gulir">
                <table class="tabel">
                    <thead><tr><th>Aplikasi</th><th>Peran</th><th>Alamat</th><th>Kabar terakhir dari Pusat</th></tr></thead>
                    <tbody>
                    @foreach (['pemberi' => 'menyediakan data', 'peminta' => 'mengambil data'] as $peran => $teks)
                        @php($a = $s[$peran])
                        <tr>
                            <td><strong>{{ $a?->nama ?? $def[$peran] }}</strong>
                                @if ($a && ! $a->aktif)<span class="lencana warn">nonaktif</span>@endif
                                @if ($a && ! $a->webhook_url)<span class="lencana warn">tanpa webhook</span>@endif
                            </td>
                            <td>{{ $teks }}</td>
                            <td class="mono">{{ $a?->base_url ?? '—' }}</td>
                            <td>
                                @php($o = $a ? ($s['kabar'][$a->kode] ?? null) : null)
                                @if (! $o)
                                    <span class="samar">—</span>
                                @elseif ($o->status === 'terkirim')
                                    <span class="lencana ok">diterima</span> <span class="samar">{{ $o->terkirim_pada?->format('d/m/Y H:i') }}</span>
                                @else
                                    <a href="{{ route('admin.webhook.show', $o) }}"><span class="lencana {{ $o->status === 'gagal' ? 'bad' : 'warn' }}">{{ $o->status === 'gagal' ? 'gagal' : 'menunggu' }}</span></a>
                                    <span class="samar">{{ $o->galat_terakhir ?: ($o->kode_http ? 'HTTP '.$o->kode_http : '') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($row?->kunci_dibuat_pada && $def['kunci'])
                <p class="samar" style="margin:8px 0 0"><small>Kunci dibuat {{ $row->kunci_dibuat_pada->format('d/m/Y H:i') }}. Kunci tidak pernah ditampilkan; hanya dikirim ke kedua aplikasi.</small></p>
            @endif
        </div>
    @endforeach

    <p class="samar"><small>Aplikasi yang belum diperbarui (deploy/update.sh) akan mengabaikan kabar ini. Website tidak menerima webhook: ia mengambil sambungan setiap jam, atau segera saat tombol <em>Sinkron sekarang</em> di Website → Integrasi Pusat diklik.</small></p>
@endsection
