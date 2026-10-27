@extends('layouts.app')

@section('judul', 'Akun')

@section('isi')
    <div class="kepala">
        <div>
            <h1>Akun</h1>
            <p class="samar">Akun login tunggal dan peran di tiap aplikasi.</p>
        </div>
        <div class="aksi">
            <a class="tombol sekunder" href="{{ route('admin.akun.massal') }}">Buat akun guru &amp; staf sekaligus</a>
            <a class="tombol sekunder" href="{{ route('admin.akun.siswa') }}">Buat akun siswa sekaligus</a>
            <a class="tombol" href="{{ route('admin.akun.create') }}">+ Tambah akun</a>
        </div>
    </div>

    <form class="pencarian" method="get" action="{{ route('admin.akun.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari username/nama…" aria-label="Cari">
        <button class="tombol sekunder" type="submit">Cari</button>
    </form>

    <div class="kartu">
        <div class="gulir">
            <table class="tabel hp-kartu">
                <thead><tr><th>Username</th><th>Nama</th><th>Tipe</th><th>Akses</th><th>Login terakhir</th><th></th></tr></thead>
                <tbody>
                @forelse ($items as $u)
                    <tr>
                        <td>
                            {{ $u->username }}
                            @if ($u->superadmin) <span class="lencana warn">superadmin</span> @endif
                            @unless ($u->aktif) <span class="lencana bad">nonaktif</span> @endunless
                        </td>
                        <td data-label="Nama">{{ $u->nama }}</td>
                        <td data-label="Tipe">{{ $u->tipe() }}</td>
                        <td data-label="Akses">
                            @foreach ($u->akses->groupBy('aplikasi_id') as $baris)
                                <div><strong>{{ $baris->first()->aplikasi?->kode }}</strong>:
                                    @foreach ($baris as $a)<span class="lencana">{{ $a->peran }}</span> @endforeach
                                </div>
                            @endforeach
                        </td>
                        <td data-label="Login terakhir">{{ $u->login_terakhir?->locale('id')->diffForHumans() ?? 'belum pernah' }}</td>
                        <td class="kanan">
                            <div class="aksi">
                                <a class="tombol sekunder kecil" href="{{ route('admin.akun.edit', $u) }}">Ubah</a>
                                @if ($u->id !== auth()->id())
                                    <form method="post" action="{{ route('admin.akun.destroy', $u) }}" data-konfirmasi="Hapus akun {{ $u->username }} secara permanen?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="tombol bahaya kecil" type="submit">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="kosong">{{ request('q') ? 'Tidak ada akun yang cocok.' : 'Belum ada akun.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links('pagination.sederhana') }}
    </div>
@endsection
