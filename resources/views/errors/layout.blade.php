<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Operations Desk</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body style="background:#f6f7f5;color:#202522;font-family:'Times New Roman',Times,'Liberation Serif',serif;margin:0">
    <main class="grid min-h-screen place-items-center px-4 py-10" style="min-height:100vh;display:grid;place-items:center">
        <div class="w-full max-w-lg rounded-lg border border-hairline bg-surface p-7" style="max-width:32rem;background:#fff;border:1px solid #e2e5e1;border-radius:8px;padding:28px">
            <div class="flex items-center gap-2.5">
                <span class="grid size-8 place-items-center rounded-md bg-forest text-[14px] font-bold text-white">OD</span>
                <span class="font-mono text-[11px] uppercase tracking-[0.12em] text-slate">Operations Desk</span>
            </div>
            <p class="mt-6 font-mono text-[12px] text-slate">Error @yield('code')</p>
            <h1 class="mt-1 text-[22px] font-semibold tracking-tight">@yield('heading')</h1>
            <p class="mt-2 text-[13.5px] leading-relaxed text-ink-soft">@yield('message')</p>
            <div class="mt-6 flex flex-wrap gap-2">
                @yield('actions')
            </div>
        </div>
    </main>
</body>
</html>
