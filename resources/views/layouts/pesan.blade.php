@if (session('sukses'))
    <div class="notif sukses" role="status">{{ session('sukses') }}</div>
@endif
@if (session('galat'))
    <div class="notif galat" role="alert">{{ session('galat') }}</div>
@endif
@if ($errors->any())
    <div class="notif galat" role="alert">
        Periksa kembali isian Anda:
        <ul>
            @foreach ($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
    </div>
@endif
