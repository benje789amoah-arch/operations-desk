@props(['title', 'crumbs' => [], 'description' => null])

<div {{ $attributes->class(['mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between']) }}>
    <div class="min-w-0">
        @if ($crumbs)
            <nav aria-label="Breadcrumb" class="mb-1.5">
                <ol class="flex flex-wrap items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.08em] text-slate">
                    @foreach ($crumbs as $label => $url)
                        <li class="flex items-center gap-1.5">
                            @if (! $loop->first)<span aria-hidden="true" class="text-rule">/</span>@endif
                            @if (is_string($url) && ! $loop->last)
                                <a href="{{ $url }}" class="hover:text-ink hover:underline">{{ is_int($label) ? $url : $label }}</a>
                            @else
                                <span @if ($loop->last) class="text-ink" aria-current="page" @endif>{{ is_int($label) ? $url : $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-3xl text-[13.5px] text-slate">{{ $description }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
