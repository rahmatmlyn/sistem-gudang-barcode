<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 flex items-center justify-center p-4">
    <main class="w-full max-w-sm bg-white rounded-2xl shadow p-6">
        <h1 class="text-2xl font-bold text-aksen">{{ config('app.name') }}</h1>
        <p class="mt-1 text-slate-600">Masuk dengan username dan password.</p>

        <form method="POST" action="/login" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="username" class="block text-sm font-semibold">Username</label>
                <input id="username" name="username" type="text" value="{{ old('username') }}"
                       autocomplete="username" autocapitalize="none" autofocus required
                       class="mt-1 w-full min-h-11 rounded-lg border border-slate-300 px-3 text-lg focus:border-aksen focus:outline-none focus:ring-2 focus:ring-aksen/30">
                @error('username')
                    <p class="mt-1 text-sm text-orange-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="mt-1 w-full min-h-11 rounded-lg border border-slate-300 px-3 text-lg focus:border-aksen focus:outline-none focus:ring-2 focus:ring-aksen/30">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" class="size-5"> Ingat saya
            </label>
            <button type="submit" class="w-full min-h-12 rounded-lg bg-aksen text-white text-lg font-semibold">
                Masuk
            </button>
        </form>
    </main>
</body>
</html>
