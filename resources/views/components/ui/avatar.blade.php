@props(['name' => '', 'size' => 'md'])

@php
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
    // A stable, muted tone per person so the same name always looks the same.
    $tones = ['bg-forest-tint text-forest', 'bg-amber-tint text-amber', 'bg-subtle text-ink', 'bg-[#e6eef4] text-[#2b4c66]', 'bg-[#efe9f3] text-[#58406b]'];
    $tone = $tones[crc32($name) % count($tones)];
    $dimension = ['sm' => 'size-6 text-[10px]', 'md' => 'size-8 text-[11.5px]', 'lg' => 'size-11 text-[14px]'][$size] ?? 'size-8 text-[11.5px]';
@endphp

<span {{ $attributes->class(["inline-grid shrink-0 place-items-center rounded-full font-semibold ring-1 ring-black/5 $tone $dimension"]) }} aria-hidden="true">{{ $initials ?: '?' }}</span>
