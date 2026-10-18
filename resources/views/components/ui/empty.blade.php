@props(['icon' => 'inbox', 'title'])

<div {{ $attributes->class(['flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="grid size-11 place-items-center rounded-full border border-hairline bg-canvas text-slate">
        <x-dynamic-component :component="'lucide-'.$icon" class="size-5" aria-hidden="true" />
    </span>
    <p class="mt-3 text-[14px] font-medium text-ink">{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-1 max-w-md text-[13px] text-slate">{{ $slot }}</div>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
