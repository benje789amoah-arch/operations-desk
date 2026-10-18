@props(['label', 'value', 'hint' => null, 'tone' => 'neutral', 'icon' => null, 'definition' => null])

@php
    $valueClass = match ($tone) {
        'positive' => 'text-forest',
        'attention' => 'text-amber',
        'critical' => 'text-crimson',
        default => 'text-ink',
    };
@endphp

<div {{ $attributes->class(['panel flex flex-col gap-2 px-4 py-3.5']) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="eyebrow">{{ $label }}</p>
        @if ($icon)
            <x-dynamic-component :component="'lucide-'.$icon" class="size-4 shrink-0 text-slate" aria-hidden="true" />
        @endif
    </div>
    <p class="num text-[26px] font-semibold leading-none tracking-tight {{ $valueClass }}">{{ $value }}</p>
    @if ($hint || $slot->isNotEmpty())
        <div class="meta">{{ $hint }}{{ $slot }}</div>
    @endif
    @if ($definition)
        <p class="sr-only">Definition: {{ $definition }}</p>
    @endif
</div>
