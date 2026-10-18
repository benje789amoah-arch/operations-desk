@php($pageName = $paginator->getPageName())

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
            <button type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" @disabled($paginator->onFirstPage()) class="btn btn-secondary btn-sm" aria-label="Previous page">
                <x-lucide-chevron-left class="size-4" />
            </button>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1.5 text-slate" aria-hidden="true">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="num grid h-8 min-w-8 place-items-center rounded-md bg-forest px-2 font-mono text-[12px] text-white" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" class="num hidden h-8 min-w-8 rounded-md px-2 font-mono text-[12px] text-slate hover:bg-subtle hover:text-ink sm:inline-block" aria-label="Go to page {{ $page }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            <button type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages()) class="btn btn-secondary btn-sm" aria-label="Next page">
                <x-lucide-chevron-right class="size-4" />
            </button>
        </div>
    @endif
</nav>
