@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}Npontu Operations Desk</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23245c46'/%3E%3Cpath d='M9 23V9h3.2l6.6 9.1V9H22v14h-3.1l-6.7-9.2V23z' fill='white'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <aside class="relative hidden overflow-hidden bg-ink p-10 text-white lg:flex lg:flex-col lg:justify-between" aria-hidden="true">
            <div class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-md bg-forest text-[16px] font-bold">N</span>
                <span class="leading-tight">
                    <span class="block text-[14px] font-semibold">Npontu</span>
                    <span class="block font-mono text-[10.5px] uppercase tracking-[0.12em] text-white/60">Operations Desk</span>
                </span>
            </div>

            <div class="max-w-md">
                <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#99d3b7]">Applications Support · Daily Operations &amp; Handover</p>
                <h1 class="mt-4 text-[30px] font-semibold leading-[1.15] tracking-[-0.02em]">Every check recorded.<br>Every handover clear.</h1>
                <p class="mt-4 text-[14px] leading-relaxed text-white/70">Record what was checked, who checked it and when, and hand over outstanding work with the full story behind it.</p>
            </div>

            {{-- A quiet register motif: decorative, so hidden from assistive technology. --}}
            <div class="space-y-2 font-mono text-[11.5px] text-white/50">
                @foreach ([['08:00', 'Daily SMS count vs logs', 'done'], ['08:15', 'Application error log review', 'done'], ['09:00', 'Failed transaction review', 'pending']] as [$time, $label, $state])
                    <div class="flex items-center gap-3 border-t border-white/10 pt-2">
                        <span>{{ $time }}</span>
                        <span class="flex-1 truncate text-white/70">{{ $label }}</span>
                        <span class="{{ $state === 'done' ? 'text-[#99d3b7]' : 'text-[#f2cf8d]' }}">{{ ucfirst($state) }}</span>
                    </div>
                @endforeach
                <p class="pt-2 text-[10.5px] text-white/40">Illustration only</p>
            </div>
        </aside>

        <main class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-[400px]">
                <div class="mb-8 flex items-center gap-2.5 lg:hidden">
                    <span class="grid size-9 place-items-center rounded-md bg-forest text-[16px] font-bold text-white">N</span>
                    <span class="leading-tight">
                        <span class="block text-[14px] font-semibold">Npontu</span>
                        <span class="block font-mono text-[10.5px] uppercase tracking-[0.12em] text-slate">Operations Desk</span>
                    </span>
                </div>
                {{ $slot }}
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
