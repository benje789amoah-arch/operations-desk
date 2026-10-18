@props(['title' => null])

@php
    use App\Support\Ability;

    $user = auth()->user();
    $nav = [
        'Operations' => [
            ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Overview', 'icon' => 'layout-dashboard'],
            ['route' => 'register', 'match' => ['register', 'activities.*'], 'label' => 'Daily register', 'icon' => 'list-checks', 'badge' => $shell['outstandingToday'] ?: null, 'badgeLabel' => 'outstanding today'],
            ['route' => 'handover.index', 'match' => 'handover.*', 'label' => 'Handover centre', 'icon' => 'arrow-left-right', 'badge' => $shell['awaitingAcknowledgement'] ?: null, 'badgeLabel' => 'awaiting acknowledgement', 'badgeTone' => 'attention'],
            ['route' => 'definitions.index', 'match' => 'definitions.*', 'label' => 'Activity definitions', 'icon' => 'workflow'],
        ],
        'Insight' => [
            ['route' => 'reports.index', 'match' => 'reports.*', 'label' => 'Reporting centre', 'icon' => 'chart-column'],
        ],
        'Administration' => array_values(array_filter([
            $user->can(Ability::MANAGE_PERSONNEL) ? ['route' => 'personnel.index', 'match' => 'personnel.*', 'label' => 'Personnel & access', 'icon' => 'users'] : null,
            $user->can(Ability::VIEW_AUDIT_LOG) ? ['route' => 'audit.index', 'match' => 'audit.*', 'label' => 'System audit log', 'icon' => 'scroll-text'] : null,
        ])),
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}Operations Desk</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23245c46'/%3E%3Ctext x='16' y='21.5' text-anchor='middle' font-family='Times New Roman,Times,serif' font-size='15' font-weight='700' fill='white'%3EOD%3C/text%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full" x-data="{ navOpen: false }" @keydown.escape.window="navOpen = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[60] btn btn-primary">Skip to main content</a>

    {{-- Mobile backdrop --}}
    <div x-cloak x-show="navOpen" x-transition.opacity class="fixed inset-0 z-40 bg-ink/40 backdrop-blur-[2px] lg:hidden" @click="navOpen = false"></div>

    {{-- Sidebar --}}
    <aside data-app-sidebar
           class="fixed inset-y-0 left-0 z-50 flex w-[256px] flex-col border-r border-hairline bg-[#f0f2ef] transition-transform duration-200 lg:translate-x-0"
           :class="navOpen ? 'translate-x-0 shadow-[var(--shadow-sheet)]' : '-translate-x-full lg:translate-x-0'"
           x-trap.noscroll="navOpen"
           aria-label="Main navigation">
        <div class="flex h-14 shrink-0 items-center gap-2.5 border-b border-hairline bg-surface px-4">
            <span class="grid size-8 place-items-center rounded-md bg-forest text-[14px] font-bold text-white" aria-hidden="true">OD</span>
            <span class="leading-tight">
                <span class="block text-[14px] font-semibold tracking-tight">Operations Desk</span>
                <span class="block font-mono text-[10.5px] uppercase tracking-[0.12em] text-slate">Applications Support</span>
            </span>
            <button type="button" class="btn btn-ghost btn-sm ml-auto lg:hidden" @click="navOpen = false" aria-label="Close navigation">
                <x-lucide-x class="size-4" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            @foreach ($nav as $section => $links)
                @continue(empty($links))
                <p class="eyebrow mb-2 mt-5 px-3 first:mt-0">{{ $section }}</p>
                <ul class="space-y-0.5">
                    @foreach ($links as $link)
                        @php($active = request()->routeIs(...(array) $link['match']))
                        <li>
                            <a href="{{ route($link['route']) }}" @class(['nav-link', 'nav-link-active' => $active]) @if ($active) aria-current="page" @endif>
                                <x-dynamic-component :component="'lucide-'.$link['icon']" class="size-[18px] shrink-0 {{ $active ? '' : 'text-slate' }}" />
                                <span class="flex-1 truncate">{{ $link['label'] }}</span>
                                @if (! empty($link['badge']))
                                    <span @class([
                                        'num rounded px-1.5 font-mono text-[11px] leading-5',
                                        'bg-white/20 text-white' => $active,
                                        'bg-amber-tint text-amber' => ! $active && ($link['badgeTone'] ?? null) === 'attention',
                                        'bg-surface text-slate ring-1 ring-hairline' => ! $active && ($link['badgeTone'] ?? null) !== 'attention',
                                    ]) title="{{ $link['badge'] }} {{ $link['badgeLabel'] }}">
                                        {{ $link['badge'] }}<span class="sr-only"> {{ $link['badgeLabel'] }}</span>
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="m-3 rounded-md border border-hairline bg-surface p-3 text-[11.5px] leading-relaxed text-slate">
            <div class="flex items-center justify-between">
                <span class="eyebrow text-[10px]">Environment</span>
                <span @class(['chip', 'chip-pending' => ! app()->isProduction(), 'chip-done' => app()->isProduction()])>{{ app()->environment() }}</span>
            </div>
            <dl class="mt-2 space-y-0.5 font-mono text-[11px]">
                <div class="flex justify-between gap-2"><dt>Timezone</dt><dd class="truncate text-ink">{{ config('operations.timezone') }} ({{ ops_tz() }})</dd></div>
                <div class="flex justify-between gap-2"><dt>Carry-over</dt><dd class="text-ink">{{ config('operations.carry_over_days') }} days</dd></div>
            </dl>
            @unless (app()->isProduction())
                <p class="mt-2 border-t border-hairline pt-2">Records here may be demonstration data.</p>
            @endunless
        </div>
    </aside>

    <div class="flex min-h-full flex-col lg:pl-[256px]">
        {{-- Top bar --}}
        <header data-app-header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-hairline bg-surface/95 px-3 backdrop-blur sm:px-5">
            <button type="button" class="btn btn-ghost btn-sm lg:hidden" @click="navOpen = true" aria-label="Open navigation">
                <x-lucide-menu class="size-5" />
            </button>

            <div class="flex min-w-0 items-center gap-4 font-mono text-[12px] text-slate">
                <span class="flex items-center gap-1.5 whitespace-nowrap">
                    <x-lucide-calendar class="size-4 text-slate" />
                    <span class="text-ink"><span class="hidden sm:inline">{{ $shell['today']->format('l, ') }}</span>{{ $shell['today']->format('d M Y') }}</span>
                </span>
                @if ($shell['shift'])
                    <span class="hidden items-center gap-1.5 whitespace-nowrap md:flex lg:hidden xl:flex" title="Current shift by the configured roster times">
                        <x-lucide-clock class="size-4" />
                        {{ $shell['shift']['label'] }} · {{ $shell['shift']['start'] }}–{{ $shell['shift']['end'] }} {{ ops_tz() }}
                    </span>
                @endif
            </div>

            <div class="ml-auto flex items-center gap-2">
                @can(Ability::RECORD_UPDATES)
                    <a href="{{ route('register') }}" class="btn btn-secondary btn-sm hidden sm:inline-flex">
                        <x-lucide-square-pen class="size-4" /> Record activity
                    </a>
                @endcan
                <a href="{{ route('handover.index') }}" class="btn btn-primary btn-sm hidden sm:inline-flex">
                    <x-lucide-clipboard-check class="size-4" /> Handover
                </a>

                <div class="relative ml-1" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="flex items-center gap-2.5 rounded-md py-1 pl-1 pr-2 hover:bg-subtle" @click="open = !open" :aria-expanded="open" aria-haspopup="menu">
                        <x-ui.avatar :name="$user->name" />
                        <span class="hidden whitespace-nowrap text-left leading-tight xl:block">
                            <span class="block text-[13px] font-medium">{{ $user->name }}</span>
                            <span class="block text-[11.5px] text-slate">{{ $user->role->label() }}</span>
                        </span>
                        <x-lucide-chevron-down class="size-4 text-slate" />
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-64 rounded-md border border-hairline bg-surface p-1.5 shadow-[var(--shadow-pop)]" role="menu">
                        <div class="border-b border-hairline px-2.5 pb-2.5 pt-1.5">
                            <p class="text-[13px] font-medium">{{ $user->name }}</p>
                            <p class="truncate text-[12px] text-slate">{{ $user->email }}</p>
                        </div>
                        <a href="{{ route('profile.show') }}" class="mt-1 flex items-center gap-2 rounded px-2.5 py-2 text-[13px] hover:bg-subtle" role="menuitem"><x-lucide-user class="size-4 text-slate" /> My profile</a>
                        <a href="{{ route('password.edit') }}" class="flex items-center gap-2 rounded px-2.5 py-2 text-[13px] hover:bg-subtle" role="menuitem"><x-lucide-key-round class="size-4 text-slate" /> Change password</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 rounded px-2.5 py-2 text-left text-[13px] text-crimson hover:bg-crimson-tint" role="menuitem"><x-lucide-log-out class="size-4" /> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="mx-auto w-full max-w-[1480px] flex-1 px-3 py-5 sm:px-6 sm:py-6" tabindex="-1">
            {{ $slot }}
        </main>
    </div>

    {{-- Session expiry notice (shown by app.js when a Livewire request returns 419) --}}
    <div id="session-expired" hidden class="fixed inset-0 z-[70] grid place-items-center bg-ink/40 p-4 backdrop-blur-[2px]" role="alertdialog" aria-labelledby="session-expired-title">
        <div class="w-full max-w-md rounded-lg border border-hairline bg-surface p-5 shadow-[var(--shadow-sheet)]">
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-md bg-amber-tint text-amber"><x-lucide-clock class="size-5" /></span>
                <div>
                    <h2 id="session-expired-title" class="section-title">Your session has expired</h2>
                    <p class="mt-1 text-slate">For security, you were signed out after a period of inactivity. Copy any text you were typing, then sign in again to continue.</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <a href="{{ route('login') }}" class="btn btn-primary">Sign in again</a>
            </div>
        </div>
    </div>

    {{-- Toasts --}}
    <div data-toasts x-data class="pointer-events-none fixed bottom-4 right-4 z-[65] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite" aria-atomic="false"
         @if (session('toast')) x-init="$store.toasts.push({ message: @js(session('toast')), tone: @js(session('toast_tone', 'positive')) })" @endif>
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div class="pointer-events-auto flex items-start gap-2.5 rounded-md border bg-surface p-3 pr-2 shadow-[var(--shadow-pop)] animate-slide-in"
                 :class="{ 'border-forest-line': toast.tone === 'positive', 'border-amber-line': toast.tone === 'attention', 'border-crimson-line': toast.tone === 'critical' }">
                <span class="mt-0.5 size-2 shrink-0 rounded-full" :class="{ 'bg-forest': toast.tone === 'positive', 'bg-amber-strong': toast.tone === 'attention', 'bg-crimson': toast.tone === 'critical' }" aria-hidden="true"></span>
                <p class="flex-1 text-[13px]" x-text="toast.message"></p>
                <button type="button" class="rounded p-0.5 text-slate hover:bg-subtle" @click="$store.toasts.dismiss(toast.id)" aria-label="Dismiss notification"><x-lucide-x class="size-4" /></button>
            </div>
        </template>
    </div>
    @livewireScripts
</body>
</html>
