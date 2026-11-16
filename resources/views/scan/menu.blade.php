@extends('scan.layout')
@section('title', 'Menu Scan')
@section('content')
    <header class="flex items-center justify-between gap-3 border-b border-garis bg-white px-4 py-3">
        <div class="flex flex-col">
            <h1 class="text-[17px] font-semibold text-aksen">Menu Scan</h1>
            <span class="text-[13px] text-redup">{{ auth()->user()->name }} · {{ auth()->user()->role->getLabel() }}</span>
        </div>
        <form method="POST" action="/logout">
            @csrf
            <button type="submit" class="min-h-11 rounded-[10px] border border-garis bg-white px-4 font-semibold">Keluar</button>
        </form>
    </header>
    <main class="grid grid-cols-2 gap-3 p-4">
        @foreach ($modes as $mode => $info)
            <a href="/scan/{{ $mode }}"
               class="flex min-h-28 flex-col justify-between gap-2 rounded-2xl border border-garis bg-white p-4 active:border-aksen">
                <span class="text-lg font-semibold">{{ $info['label'] }}</span>
                <span class="text-sm text-redup">{{ $info['keterangan'] }}</span>
            </a>
        @endforeach
    </main>
    @if (auth()->user()->role->bisaAksesPanel())
        <a href="/admin" class="mx-4 mb-4 text-center text-sm font-semibold text-aksen">Buka panel admin</a>
    @endif
@endsection
