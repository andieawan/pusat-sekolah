<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('judul', 'Masuk') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/pusat.css') }}?v={{ @filemtime(public_path('css/pusat.css')) }}">
</head>
<body class="polos">
<div class="kotak">
    @yield('isi')
</div>
</body>
</html>
