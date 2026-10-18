@php use App\Enums\Recurrence; use App\Support\Ability; @endphp

<div>
    <x-ui.page-header
        :crumbs="['Operations', 'Activity definitions']"
        title="Activity definitions"
        description="The checks the desk performs and how often they recur. Each definition creates one register entry per applicable day; editing a definition never rewrites past records.">
        <x-slot:actions>
            @can('create', App\Models\ActivityDefinition::class)
                <a href="{{ route('definitions.create') }}" class="btn btn-primary"><x-lucide-plus class="size-4" /> New definition</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
        <section class="panel min-w-0 overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-hairline p-2.5 md:flex-row md:items-center">
                <div class="segmented shrink-0" role="tablist" aria-label="Definition status">
                    <button type="button" role="tab" wire:click="$set('status', 'active')" aria-selected="{{ $status === 'active' ? 'true' : 'false' }}" @class(['segment', 'segment-active' => $status === 'active'])>Active <span class="num font-mono text-[11px] text-slate">{{ $totals['active'] }}</span></button>
                    <button type="button" role="tab" wire:click="$set('status', 'archived')" aria-selected="{{ $status === 'archived' ? 'true' : 'false' }}" @class(['segment', 'segment-active' => $status === 'archived'])>Archived <span class="num font-mono text-[11px] text-slate">{{ $totals['archived'] }}</span></button>
                    <button type="button" role="tab" wire:click="$set('status', 'all')" aria-selected="{{ $status === 'all' ? 'true' : 'false' }}" @class(['segment', 'segment-active' => $status === 'all'])>All</button>
                </div>
                <div class="relative flex-1">
                    <x-lucide-search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-slate" />
                    <label for="definition-search" class="sr-only">Search definitions</label>
                    <input id="definition-search" type="search" wire:model.live.debounce.350ms="search" class="input pl-8" placeholder="Search code, title or procedure…">
                </div>
                <div class="grid grid-cols-2 gap-2 md:flex">
                    <label for="definition-category" class="sr-only">Category</label>
                    <select id="definition-category" wire:model.live="category" class="input md:w-44">
                        <option value="">All categories</option>
                        @foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                    </select>
                    <label for="definition-recurrence" class="sr-only">Recurrence</label>
                    <select id="definition-recurrence" wire:model.live="recurrence" class="input md:w-36">
                        <option value="">Any schedule</option>
                        @foreach (Recurrence::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>

            @if ($definitions->isEmpty())
                <x-ui.empty icon="workflow" :title="$search || $category || $recurrence ? 'No definitions match these filters' : ($status === 'archived' ? 'No archived definitions' : 'No activity definitions yet')">
                    @if ($search || $category || $recurrence)
                        <x-slot:action><button type="button" wire:click="resetFilters" class="btn btn-secondary btn-sm"><x-lucide-filter-x class="size-4" /> Clear filters</button></x-slot:action>
                    @elseif ($status !== 'archived')
                        Definitions describe the recurring checks the desk performs, such as the daily SMS count reconciliation.
                    @endif
                </x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="table-ops">
                        <thead>
                            <tr>
                                @foreach (['code' => 'Code', 'title' => 'Activity'] as $column => $label)
                                    <th scope="col" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                        <button type="button" wire:click="sortBy('{{ $column }}')" class="flex items-center gap-1 uppercase hover:text-ink">{{ $label }}
                                            @if ($sort === $column)<x-dynamic-component :component="$direction === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3" />@else<x-lucide-arrow-up-down class="size-3 opacity-40" />@endif
                                        </button>
                                    </th>
                                @endforeach
                                <th scope="col" class="hidden md:table-cell">Schedule</th>
                                <th scope="col" aria-sort="{{ $sort === 'priority' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <button type="button" wire:click="sortBy('priority')" class="flex items-center gap-1 uppercase hover:text-ink">Priority <x-lucide-arrow-up-down class="size-3 opacity-40" /></button>
                                </th>
                                <th scope="col" class="hidden lg:table-cell">Default owner</th>
                                <th scope="col" class="hidden lg:table-cell" aria-sort="{{ $sort === 'updated_at' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <button type="button" wire:click="sortBy('updated_at')" class="flex items-center gap-1 uppercase hover:text-ink">Last changed <x-lucide-arrow-up-down class="size-3 opacity-40" /></button>
                                </th>
                                <th scope="col" class="w-24 text-right"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($definitions as $definition)
                                <tr wire:key="definition-{{ $definition->id }}" @class(['opacity-70' => $definition->isArchived()])>
                                    <td class="mono whitespace-nowrap text-[12.5px] font-semibold">{{ $definition->code }}</td>
                                    <td class="min-w-[16rem]">
                                        <a href="{{ route('definitions.show', $definition) }}" class="font-medium hover:underline">{{ $definition->title }}</a>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11.5px] text-slate">
                                            {{ $definition->category?->name ?? 'Uncategorised' }} · {{ $definition->instances_count }} record{{ $definition->instances_count === 1 ? '' : 's' }}
                                            @if ($definition->isArchived())<span class="chip chip-neutral"><x-lucide-archive class="size-3" /> Archived</span>@endif
                                        </p>
                                    </td>
                                    <td class="hidden whitespace-nowrap md:table-cell">
                                        <p class="text-[12.5px]">{{ $definition->recurrence->describe($definition->recurrence_days ?? []) }}</p>
                                        <p class="font-mono text-[11.5px] text-slate">{{ $definition->scheduled_time ? substr($definition->scheduled_time, 0, 5) : 'Any time' }}@if ($definition->due_time) → {{ substr($definition->due_time, 0, 5) }}@endif</p>
                                    </td>
                                    <td><x-ui.priority :priority="$definition->priority" /></td>
                                    <td class="hidden text-[12.5px] lg:table-cell">{{ $definition->defaultAssignee?->name ?? '—' }}</td>
                                    <td class="hidden whitespace-nowrap font-mono text-[11.5px] text-slate lg:table-cell">{{ ops_format($definition->updated_at, 'd M Y') }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        <a href="{{ route('definitions.show', $definition) }}" class="btn btn-ghost btn-sm" aria-label="View {{ $definition->code }}"><x-lucide-eye class="size-4" /></a>
                                        @can('update', $definition)
                                            <a href="{{ route('definitions.edit', $definition) }}" class="btn btn-ghost btn-sm" aria-label="Edit {{ $definition->code }}"><x-lucide-pencil class="size-4" /></a>
                                            <button type="button" wire:click="toggleArchived({{ $definition->id }})"
                                                    wire:confirm="{{ $definition->isArchived() ? 'Restore '.$definition->code.'? It will be scheduled again from today.' : 'Archive '.$definition->code.'? No new instances will be created. Existing records and history are kept.' }}"
                                                    class="btn btn-ghost btn-sm" aria-label="{{ $definition->isArchived() ? 'Restore' : 'Archive' }} {{ $definition->code }}" title="{{ $definition->isArchived() ? 'Restore' : 'Archive' }}">
                                                <x-dynamic-component :component="$definition->isArchived() ? 'lucide-archive-restore' : 'lucide-archive'" class="size-4" />
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $definitions->links() }}
            @endif
        </section>

        <aside class="space-y-4">
            <section class="panel">
                <div class="panel-header"><h2 class="section-title">Categories</h2></div>
                <ul class="divide-y divide-hairline">
                    @forelse ($categories as $cat)
                        <li class="flex items-center justify-between gap-2 px-4 py-2 text-[13px]">
                            <button type="button" wire:click="$set('category', '{{ $cat->id }}')" class="truncate text-left hover:underline">{{ $cat->name }}</button>
                            <span class="num font-mono text-[11.5px] text-slate">{{ $cat->definitions_count }}</span>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-[13px] text-slate">No categories yet.</li>
                    @endforelse
                </ul>
                @can(Ability::MANAGE_DEFINITIONS)
                    <form wire:submit="addCategory" class="border-t border-hairline p-3">
                        <label for="new-category" class="field-label">Add a category</label>
                        <div class="flex gap-2">
                            <input id="new-category" type="text" wire:model="newCategory" class="input" maxlength="80" placeholder="e.g. Card Processing" @error('newCategory') aria-invalid="true" @enderror>
                            <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="addCategory">Add</button>
                        </div>
                        <x-ui.error for="newCategory" />
                    </form>
                @endcan
            </section>

            <section class="panel p-4 text-[12.5px] leading-relaxed text-slate">
                <h2 class="section-title mb-1.5 flex items-center gap-1.5 text-ink"><x-lucide-info class="size-4" /> How scheduling works</h2>
                <p>At 00:01 {{ ops_tz() }} each day the scheduler creates one register entry for every active definition due that day. It runs safely more than once: an entry is unique per definition and date, so nothing is ever duplicated or reset.</p>
                <p class="mt-2">Archiving stops future entries only. Pending entries stay on the register until someone closes them.</p>
            </section>
        </aside>
    </div>
</div>
