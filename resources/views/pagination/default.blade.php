<nav role="navigation" aria-label="Pagination" class="flex flex-col gap-3 border-t border-hairline px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="meta num">
        @if ($paginator->total())
            Showing <span class="font-medium text-ink">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> of <span class="font-medium text-ink">{{ $paginator->total() }}</span>
        @else
            No records
        @endif
    </p>

    @if ($paginator->hasPages())
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true"><x-lucide-chevron-left class="size-4" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-secondary btn-sm" aria-label="Previous page"><x-lucide-chevron-left class="size-4" /></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1.5 text-slate">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="num grid h-8 min-w-8 place-items-center rounded-md bg-forest px-2 font-mono text-[12px] text-white" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="num hidden h-8 min-w-8 place-items-center rounded-md px-2 font-mono text-[12px] text-slate hover:bg-subtle sm:grid">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-secondary btn-sm" aria-label="Next page"><x-lucide-chevron-right class="size-4" /></a>
            @else
                <span class="btn btn-secondary btn-sm opacity-50" aria-disabled="true"><x-lucide-chevron-right class="size-4" /></span>
            @endif
        </div>
    @endif
</nav>
