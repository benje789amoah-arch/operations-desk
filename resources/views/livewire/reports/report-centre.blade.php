@php
    use App\Support\Ability;
    $available = $report->filters();
    $isPaginated = $results instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $rows = $isPaginated ? $results->getCollection() : ($results ?? collect());
    $presetLabels = ['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'Last 7 days', 'month' => 'Month to date', 'last-month' => 'Last month', '30' => 'Last 30 days'];
@endphp

<div>
    <x-ui.page-header :crumbs="['Insight', 'Reporting centre' => route('reports.index'), $report->title()]" :title="$report->title()" :description="$report->description()">
        <x-slot:actions>
            <button type="button" onclick="window.print()" class="btn btn-secondary no-print"><x-lucide-printer class="size-4" /> Print</button>
            @if ($canExport && $filters)
                <a href="{{ route('reports.export', ['type' => $report->key()] + $filters->toQuery()) }}" class="btn btn-primary no-print"><x-lucide-download class="size-4" /> Export CSV</a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Report type tabs --}}
    <nav class="no-print mb-3 overflow-x-auto" aria-label="Report type">
        <div class="segmented">
            @foreach ($reports as $r)
                <a href="{{ route('reports.show', ['type' => $r->key(), 'from' => $from, 'to' => $to]) }}" @class(['segment', 'segment-active' => $r->key() === $report->key()]) @if ($r->key() === $report->key()) aria-current="page" @endif>{{ $r->title() }}</a>
            @endforeach
        </div>
    </nav>

    {{-- Filters --}}
    <form wire:submit="run" class="panel no-print mb-4 p-4" aria-label="Report filters">
        <div class="mb-3 flex flex-wrap items-center gap-1.5">
            <span class="meta mr-1">Presets:</span>
            @foreach ($presetLabels as $key => $label)
                <button type="button" wire:click="applyPreset('{{ $key }}')" class="btn btn-ghost btn-sm border border-hairline">{{ $label }}</button>
            @endforeach
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            <div>
                <label for="report-from" class="field-label">Start date</label>
                <input id="report-from" type="date" wire:model="from" class="input font-mono" @error('from') aria-invalid="true" aria-describedby="from-error" @enderror>
                <x-ui.error for="from" />
            </div>
            <div>
                <label for="report-to" class="field-label">End date</label>
                <input id="report-to" type="date" wire:model="to" class="input font-mono" @error('to') aria-invalid="true" aria-describedby="to-error" @enderror>
                <x-ui.error for="to" />
            </div>
            @if (in_array('definition', $available, true))
                <div>
                    <label for="report-definition" class="field-label">Activity</label>
                    <select id="report-definition" wire:model="definition" class="input">
                        <option value="">All activities</option>
                        @foreach ($definitions as $d)<option value="{{ $d->id }}">{{ $d->code }} · {{ \Illuminate\Support\Str::limit($d->title, 40) }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if (in_array('category', $available, true))
                <div>
                    <label for="report-category" class="field-label">Category</label>
                    <select id="report-category" wire:model="category" class="input">
                        <option value="">All categories</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if (in_array('status', $available, true))
                <div>
                    <label for="report-status" class="field-label">Status</label>
                    <select id="report-status" wire:model="status" class="input">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <x-ui.error for="status" />
                </div>
            @endif
            @if (in_array('user', $available, true))
                <div>
                    <label for="report-user" class="field-label">Personnel</label>
                    <select id="report-user" wire:model="user" class="input" @if ($type === 'personnel' && auth()->user()->cannot(Ability::VIEW_TEAM_ACTIVITY)) disabled @endif>
                        @if ($type !== 'personnel' || auth()->user()->can(Ability::VIEW_TEAM_ACTIVITY))<option value="">{{ $type === 'personnel' ? 'Whole team (summary)' : 'Anyone' }}</option>@endif
                        @foreach ($people as $p)<option value="{{ $p->id }}">{{ $p->name }}{{ $p->is_active ? '' : ' (inactive)' }}</option>@endforeach
                    </select>
                    @if ($type === 'personnel' && auth()->user()->cannot(Ability::VIEW_TEAM_ACTIVITY))<p class="field-hint">You can view your own activity.</p>@endif
                </div>
            @endif
            @if (in_array('basis', $available, true))
                <div>
                    <label for="report-basis" class="field-label">Date basis</label>
                    <select id="report-basis" wire:model="basis" class="input">
                        <option value="operational">Operational date</option>
                        <option value="recorded">Time recorded</option>
                    </select>
                </div>
            @endif
            @if (in_array('order', $available, true))
                <div>
                    <label for="report-order" class="field-label">Order</label>
                    <select id="report-order" wire:model="order" class="input">
                        <option value="asc">Oldest first</option>
                        <option value="desc">Newest first</option>
                    </select>
                </div>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="run" class="flex items-center gap-2"><x-lucide-play class="size-4" /> Run report</span>
                <span wire:loading wire:target="run" class="flex items-center gap-2"><x-lucide-loader-circle class="size-4 animate-spin" /> Running…</span>
            </button>
            <button type="button" wire:click="resetFilters" class="btn btn-ghost"><x-lucide-filter-x class="size-4" /> Reset filters</button>
            <span wire:loading.delay class="meta flex items-center gap-1.5"><x-lucide-loader-circle class="size-3.5 animate-spin" /> Querying records…</span>
        </div>
    </form>

    @if (! $filters)
        <div class="panel">
            <x-ui.empty icon="circle-alert" title="Check the report filters">Correct the highlighted fields and run the report again.</x-ui.empty>
        </div>
    @else
        {{-- Applied filters (also printed) --}}
        <section class="panel mb-4 px-4 py-3" aria-label="Applied filters">
            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1.5 text-[12.5px]">
                <p class="print-only w-full text-[15px] font-semibold">Npontu Operations Desk · {{ $report->title() }}</p>
                @foreach ($filters->describe() as $label => $value)
                    <p><span class="text-slate">{{ $label }}:</span> <span class="font-medium">{{ $value }}</span></p>
                @endforeach
                <p class="text-slate">Prepared {{ ops_datetime(now()) }} {{ ops_tz() }} by {{ auth()->user()->name }}</p>
                @if ($report->key() === 'pending')
                    <p><span class="text-slate">Cutoff:</span> <span class="font-mono font-medium">{{ ops_datetime($report->cutoff($filters)) }} {{ ops_tz() }}</span></p>
                @endif
            </div>
        </section>

        {{-- Summary --}}
        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($summary as $stat)
                <x-ui.stat :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint'] ?? null" :tone="$stat['tone'] ?? 'neutral'" />
            @endforeach
        </div>

        @if ($report->key() === 'trend' && $rows->sum('total') > 0)
            @php($series = ['labels' => $rows->map(fn ($r) => $r->date->format('d M'))->values(), 'done' => $rows->pluck('done')->values(), 'pending' => $rows->pluck('pending')->values(), 'notUpdated' => $rows->pluck('not_updated')->values(), 'total' => $rows->pluck('total')->values(), 'rate' => $rows->pluck('rate')->values()])
            <section class="panel mb-4 p-4" aria-label="Completion trend chart">
                <h2 class="section-title mb-3">Status at close of day</h2>
                <div class="h-64" wire:key="chart-{{ md5(json_encode($series)) }}" x-data="trendChart(@js($series))">
                    <canvas x-ref="canvas" role="img" aria-label="Stacked bar chart of activities done, pending and not updated at the close of each day. The same figures are in the table below."></canvas>
                </div>
            </section>
        @endif

        <section class="panel overflow-hidden">
            <div class="panel-header">
                <h2 class="section-title">Results</h2>
                <p class="meta num">{{ $isPaginated ? $results->total() : $rows->count() }} row{{ ($isPaginated ? $results->total() : $rows->count()) === 1 ? '' : 's' }}</p>
            </div>

            @if ($rows->isEmpty() || ($report->key() === 'trend' && $rows->sum('total') === 0))
                <x-ui.empty icon="search" title="No activity updates were recorded for this period">
                    Nothing matches these filters between {{ $filters->from->format('d M Y') }} and {{ $filters->to->format('d M Y') }}. Try a wider date range or fewer filters.
                </x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    @switch($report->key())
                        @case('daily')
                            <table class="table-ops">
                                <thead><tr><th>Date</th><th>Activity</th><th class="hidden lg:table-cell">Responsible</th><th>At close</th><th>Now</th><th class="hidden md:table-cell">Latest remark</th><th>Last update</th></tr></thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr wire:key="daily-{{ $row->id }}">
                                            <td class="whitespace-nowrap font-mono text-[12px]">{{ $row->operational_date->format('d M Y') }}</td>
                                            <td><a href="{{ route('activities.show', $row) }}" class="mono block text-[11.5px] text-slate hover:underline">{{ $row->reference() }}</a><span class="font-medium">{{ $row->definition->title }}</span></td>
                                            <td class="hidden text-[12.5px] lg:table-cell">{{ $row->assignee?->name ?? '—' }}</td>
                                            <td><x-ui.status :state="$row->status_at_close ?? 'not_updated'" /></td>
                                            <td><x-ui.status :state="$row->wasReopened() ? 'reopened' : $row->displayState()" /></td>
                                            <td class="hidden max-w-xs md:table-cell"><p class="line-clamp-2 text-[12.5px] text-ink-soft">{{ $row->latestUpdate?->remark ?? '—' }}</p></td>
                                            <td class="whitespace-nowrap text-[12.5px]">{{ $row->latestUpdate?->updater_name ?? '—' }}<span class="block font-mono text-[11.5px] text-slate">{{ $row->latestUpdate ? ops_format($row->latestUpdate->created_at, 'd M H:i:s') : '' }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @break

                        @case('history')
                            <table class="table-ops">
                                <thead><tr><th>Recorded</th><th>Activity</th><th>Change</th><th class="hidden md:table-cell">Remark</th><th>Updated by</th></tr></thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr wire:key="history-{{ $row->id }}">
                                            <td class="whitespace-nowrap"><span class="mono block text-[11.5px] text-slate">UPD-{{ str_pad($row->id, 6, '0', STR_PAD_LEFT) }}</span><span class="num font-mono text-[12px]">{{ ops_format($row->created_at, 'd M Y H:i:s') }}</span></td>
                                            <td><a href="{{ route('activities.show', $row->activity_instance_id) }}" class="mono block text-[11.5px] text-slate hover:underline">{{ $row->instance->reference() }}</a><span class="font-medium">{{ $row->instance->definition->title }}</span><span class="block text-[11.5px] text-slate">{{ $row->instance->definition->category?->name }}</span></td>
                                            <td class="whitespace-nowrap"><span class="flex items-center gap-1"><x-ui.status :state="$row->previous_status?->value ?? 'not_updated'" /><x-lucide-arrow-right class="size-3 text-slate" /><x-ui.status :state="$row->isReopening() ? 'reopened' : $row->new_status" /></span></td>
                                            <td class="hidden max-w-sm md:table-cell"><p class="text-[12.5px] text-ink-soft">{{ $row->remark }}</p>@if ($row->supporting_details)<p class="mt-1 font-mono text-[11px] text-slate">{{ $row->supporting_details }}</p>@endif</td>
                                            <td class="text-[12.5px]">{{ $row->updater_name }}<span class="block text-[11px] text-slate">{{ collect([$row->updater_staff_number, $row->updater_designation])->filter()->join(' · ') }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @break

                        @case('pending')
                            @php($cutoff = $report->cutoff($filters))
                            <table class="table-ops">
                                <thead><tr><th>Date</th><th>Activity</th><th class="hidden lg:table-cell">Responsible</th><th>At cutoff</th><th class="hidden md:table-cell">Remark at cutoff</th><th>Last updated (at cutoff)</th><th class="text-right">Days</th><th>Since</th></tr></thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php($atCutoff = $row->getRelation('cutoffUpdate'))
                                        <tr wire:key="pending-{{ $row->id }}">
                                            <td class="whitespace-nowrap font-mono text-[12px]">{{ $row->operational_date->format('d M Y') }}</td>
                                            <td><a href="{{ route('activities.show', $row) }}" class="mono block text-[11.5px] text-slate hover:underline">{{ $row->reference() }}</a><span class="font-medium">{{ $row->definition->title }}</span></td>
                                            <td class="hidden text-[12.5px] lg:table-cell">{{ $row->assignee?->name ?? 'Unassigned' }}</td>
                                            <td><x-ui.status :state="$atCutoff ? 'pending' : 'not_updated'" /></td>
                                            <td class="hidden max-w-xs md:table-cell"><p class="line-clamp-3 text-[12.5px] text-ink-soft">{{ $atCutoff?->remark ?? 'No update before the cutoff' }}</p></td>
                                            <td class="whitespace-nowrap text-[12.5px]">{{ $atCutoff?->updater_name ?? '—' }}<span class="block font-mono text-[11.5px] text-slate">{{ $atCutoff ? ops_format($atCutoff->created_at, 'd M H:i') : '' }}</span></td>
                                            <td class="num text-right font-mono">{{ $report->daysOutstanding($row, $cutoff) }}</td>
                                            <td>@if ($row->isDone())<span class="chip chip-done"><x-lucide-check class="size-3" /> Done {{ ops_format($row->last_updated_at, 'd M') }}</span>@else<span class="text-[12px] text-slate">Still open</span>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @break

                        @case('personnel')
                            @if ($filters->userId)
                                <table class="table-ops">
                                    <thead><tr><th>Recorded</th><th>Activity</th><th class="hidden sm:table-cell">Op. date</th><th>Change</th><th class="hidden md:table-cell">Remark</th></tr></thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr wire:key="person-{{ $row->id }}">
                                                <td class="num whitespace-nowrap font-mono text-[12px]">{{ ops_format($row->created_at, 'd M Y H:i:s') }}</td>
                                                <td><a href="{{ route('activities.show', $row->activity_instance_id) }}" class="mono block text-[11.5px] text-slate hover:underline">{{ $row->instance->reference() }}</a><span class="font-medium">{{ $row->instance->definition->title }}</span></td>
                                                <td class="hidden whitespace-nowrap font-mono text-[12px] sm:table-cell">{{ $row->instance->operational_date->format('d M') }}</td>
                                                <td class="whitespace-nowrap"><x-ui.status :state="$row->isReopening() ? 'reopened' : $row->new_status" /></td>
                                                <td class="hidden max-w-sm md:table-cell"><p class="line-clamp-2 text-[12.5px] text-ink-soft">{{ $row->remark }}</p></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <table class="table-ops">
                                    <thead><tr><th>Person</th><th class="text-right">Updates</th><th class="text-right">Activities</th><th class="text-right">Done</th><th class="text-right">Pending</th><th>Last update</th><th class="no-print"><span class="sr-only">Detail</span></th></tr></thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr wire:key="team-{{ $row->user?->id }}">
                                                <td><span class="flex items-center gap-2"><x-ui.avatar :name="$row->user?->name ?? '?'" size="sm" /><span><span class="font-medium">{{ $row->user?->name }}</span><span class="block text-[11.5px] text-slate">{{ $row->user?->role->label() }}{{ $row->user && ! $row->user->is_active ? ' · inactive' : '' }}</span></span></span></td>
                                                <td class="num text-right font-mono">{{ $row->updates }}</td>
                                                <td class="num text-right font-mono">{{ $row->instances }}</td>
                                                <td class="num text-right font-mono text-forest">{{ $row->done }}</td>
                                                <td class="num text-right font-mono text-amber">{{ $row->pending }}</td>
                                                <td class="whitespace-nowrap font-mono text-[12px]">{{ ops_datetime($row->last_at) }}</td>
                                                <td class="no-print"><button type="button" wire:click="$set('user', '{{ $row->user?->id }}')" class="btn btn-ghost btn-sm">Detail <x-lucide-arrow-right class="size-3.5" /></button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                            @break

                        @case('trend')
                            <table class="table-ops">
                                <thead><tr><th>Operational date</th><th class="text-right">Scheduled</th><th class="text-right">Done at close</th><th class="text-right">Pending</th><th class="text-right">Not updated</th><th class="w-48">Completion</th></tr></thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr wire:key="trend-{{ $row->date->format('Ymd') }}">
                                            <td class="whitespace-nowrap font-mono text-[12px]">{{ $row->date->format('D d M Y') }}</td>
                                            <td class="num text-right font-mono">{{ $row->total }}</td>
                                            <td class="num text-right font-mono text-forest">{{ $row->done }}</td>
                                            <td class="num text-right font-mono text-amber">{{ $row->pending }}</td>
                                            <td class="num text-right font-mono">{{ $row->not_updated }}</td>
                                            <td>@if ($row->total)<span class="flex items-center gap-2"><x-ui.progress :value="$row->rate" :label="'Completion on '.$row->date->format('d M')" class="flex-1" /><span class="num w-12 text-right font-mono text-[12px]">{{ $row->rate }}%</span></span>@else<span class="text-[12px] text-slate">Nothing scheduled</span>@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @break
                    @endswitch
                </div>
                @if ($isPaginated)
                    <div class="no-print">{{ $results->links() }}</div>
                @endif
            @endif
        </section>

        <details class="panel no-print mt-4 p-4 text-[12.5px]">
            <summary class="cursor-pointer font-medium"><span class="inline-flex items-center gap-1.5"><x-lucide-circle-help class="size-4 text-slate" /> How this report is calculated</span></summary>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-ink-soft">
                @foreach ($report->method() as $rule)<li>{{ $rule }}</li>@endforeach
                <li>All times are shown in {{ config('operations.timezone') }} ({{ ops_tz() }}); timestamps are stored in UTC.</li>
            </ul>
        </details>
    @endif
</div>
