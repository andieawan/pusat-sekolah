@extends('layouts.app')

@section('judul', 'Webhook #'.$o->id)

@section('isi')
    <div class="kepala">
        <div>
            <h1>Webhook #{{ $o->id }}</h1>
            <p class="samar">{{ $o->aplikasi?->nama }} · <span class="mono">{{ $o->event }}</span></p>
        </div>
        <a class="tombol sekunder" href="{{ route('admin.webhook.index') }}">Kembali</a>
    </div>

    <div class="kartu">
        <table class="tabel">
            <tbody>
            <tr><th>ID pengiriman</th><td class="mono">{{ $o->uuid }}</td></tr>
            <tr><th>Status</th><td>{{ $o->status }}</td></tr>
            <tr><th>Percobaan</th><td>{{ $o->percobaan }}</td></tr>
            <tr><th>Kode HTTP</th><td>{{ $o->kode_http ?? '—' }}</td></tr>
            <tr><th>Galat terakhir</th><td>{{ $o->galat_terakhir ?: '—' }}</td></tr>
            <tr><th>Terkirim</th><td>{{ $o->terkirim_pada?->format('d/m/Y H:i:s') ?? '—' }}</td></tr>
            <tr><th>Dibuat</th><td>{{ $o->created_at?->format('d/m/Y H:i:s') }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="kartu">
        <h2>Payload</h2>
        <pre class="kode">{{ json_encode($o->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
@endsection
