<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1B5FD0">
    <title>@yield('title', 'Scan') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/scan/app.js'])
</head>
<body class="min-h-dvh bg-latar font-sans text-teks antialiased">
    <div class="mx-auto flex min-h-dvh max-w-xl flex-col">
        @yield('content')
    </div>

    <div x-data x-cloak x-show="!$store.scan.online" role="alert"
         class="fixed inset-x-0 bottom-0 z-50 bg-gagal px-4 py-3 text-center text-base font-semibold text-white">
        Koneksi terputus — scan dikirim ulang otomatis saat tersambung.
    </div>
    <style>[x-cloak]{display:none!important}</style>
</body>
</html>
