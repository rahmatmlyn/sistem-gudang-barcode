{{-- Placeholder M1; diganti layar menu scan penuh di M7. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 p-4">
    <header class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-aksen">Menu Scan</h1>
        <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="min-h-11 px-4 rounded-lg border border-slate-300 bg-white">Keluar</button>
        </form>
    </header>
    <p class="mt-4">Halo, {{ auth()->user()->name }} ({{ auth()->user()->role->getLabel() }}).</p>
</body>
</html>
