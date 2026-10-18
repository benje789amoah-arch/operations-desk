@php
    use App\Support\Ability;
    $user = auth()->user();
    $maxTrend = max(1, $trend->max('total'));
@endphp

{{-- Refreshes every minute while someone is using the page; pauses when idle so the session can still time out. --}}
<div x-data="deskRefresh({{ (int) config('session.lifetime') }})">
    {{-- Greeting and operational period --}}
    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow mb-1.5">{{ $today->format('l, j F Y') }}</p>
            <h1 class="page-title">{{ $this->greeting() }}, {{ $user->firstName() }}. Here is today's support activity.</h1>
            <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-slate">
                <span class="flex items-center gap-1.5"><x-lucide-calendar-days class="size-4" /> Operational date {{ $today->format('d M Y') }}</span>
                @if ($shift)
                    <span class="flex items-center gap-1.5"><x-lucide-clock class="size-4" /> {{ $shift['label'] }} ({{ $shift['start'] }}–{{ $shift['end'] }} {{ ops_tz() }})</span>
                @endif
                <span class="flex items-center gap-1.5" title="Refreshes every minute while you are using the page; pauses when you are away"><x-lucide-refresh-cw class="size-3.5" /> Refreshed {{ ops_time(now()) }}</span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('handover.index') }}" class="btn btn-secondary"><x-lucide-clipboard-check class="size-4" /> View handover</a>
            @can(Ability::RECORD_UPDATES)
                <a href="{{ route('register', ['state' => 'not_updated']) }}" class="btn btn-primary"><x-lucide-square-pen class="size-4" /> Record activity</a>
            @endcan
        </div>
    </div>

    {{-- Summary: definitions in DeskOverview --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Expected today" :value="$metrics['expected']" icon="list-checks"
                   :hint="$metrics['expected'] ? 'Activity instances scheduled for today' : 'Nothing scheduled today'"
                   definition="Activity instances whose operational date is today." />
        <x-ui.stat label="Completed" :value="$metrics['done']" icon="circle-check" tone="positive" definition="Today's instances whose current status is Done.">
            <div class="mt-1 flex items-center gap-2">
                <x-ui.progress :value="$metrics['completion']" label="Today's completion" class="flex-1" />
                <span class="num font-mono text-ink">{{ $metrics['completion'] }}%</span>
            </div>
        </x-ui.stat>
        <x-ui.stat label="Still pending" :value="$metrics['pending'] + $metrics['not_updated']" icon="circle-dot" tone="attention"
                   :hint="$metrics['pending'].' updated · '.$metrics['not_updated'].' not yet updated'"
                   definition="Today's instances that are not Done, split by whether anyone has updated them." />
        <x-ui.stat label="Updated today" :value="$metrics['updated']" icon="history"
                   :hint="$metrics['updates'].' update'.($metrics['updates'] === 1 ? '' : 's').' recorded, each activity counted once'"
                   definition="Distinct activities, of any date, that received at least one update today." />
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0 space-y-4">
            {{-- Requires attention --}}
            <section class="panel" aria-labelledby="attention-title">
                <div class="panel-header">
                    <h2 id="attention-title" class="section-title flex items-center gap-2">
                        <x-lucide-triangle-alert class="size-4 text-amber" /> Requires attention
                        <span class="chip {{ $attention->isEmpty() ? 'chip-done' : 'chip-pending' }} num">{{ $attention->count() }}</span>
                    </h2>
                    <p class="meta">Critical, flagged, reopened, past due, or carried over from the last {{ config('operations.carry_over_days') }} days</p>
                </div>
                @if ($attention->isEmpty())
                    <x-ui.empty icon="badge-check" title="Nothing needs follow-up right now">
                        No pending activity is flagged, reopened, past due, critical or carried over.
                    </x-ui.empty>
                @else
                    <ul class="divide-y divide-hairline">
                        @foreach ($attention->take(6) as $item)
                            <li wire:key="attention-{{ $item->id }}" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
                                <div class="min-w-0 flex-1 border-l-[3px] {{ $item->definition->priority->value === 'critical' ? 'border-crimson' : 'border-amber-strong' }} pl-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="mono text-[11.5px] font-semibold text-slate">{{ $item->definition->code }}</span>
                                        @foreach ($item->attentionReasons($today) as $reason)
                                            <span class="chip {{ $reason === 'Critical priority' ? 'chip-critical' : 'chip-pending' }}">{{ $reason }}</span>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 font-medium">{{ $item->definition->title }}</p>
                                    @if ($item->latestUpdate)
                                        <p class="mt-1.5 line-clamp-2 text-[12.5px] leading-relaxed text-ink-soft">“{{ $item->latestUpdate->remark }}”</p>
                                        <p class="mt-1 text-[12px] text-slate">Last updated by <span class="font-medium text-ink-soft">{{ $item->latestUpdate->updater_name }}</span> at <span class="font-mono">{{ ops_is_today($item->latestUpdate->created_at) ? ops_time($item->latestUpdate->created_at) : ops_format($item->latestUpdate->created_at, 'd M H:i') }}</span></p>
                                    @else
                                        <p class="mt-1.5 text-[12.5px] text-slate">No update recorded yet{{ $item->assignee ? ' · responsible: '.$item->assignee->name : '' }}.</p>
                                    @endif
                                </div>
                                <a href="{{ route('register', ['date' => $item->operational_date->format('Y-m-d'), 'activity' => $item->id]) }}" class="btn btn-secondary btn-sm shrink-0 self-start">Review <x-lucide-arrow-right class="size-3.5" /></a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($attention->count() > 6)
                        <a href="{{ route('handover.index') }}" class="block border-t border-hairline px-4 py-2.5 text-[12.5px] font-medium text-forest hover:bg-wash">See all {{ $attention->count() }} in the handover centre →</a>
                    @endif
                @endif
            </section>

            {{-- Today's activities --}}
            <section class="panel" aria-labelledby="today-title">
                <div class="panel-header">
                    <h2 id="today-title" class="section-title">Today's activity</h2>
                    <div class="segmented overflow-x-auto" role="tablist" aria-label="Filter today's activity">
                        @foreach (['all' => 'All', 'not_updated' => 'Not updated', 'pending' => 'Pending', 'done' => 'Done'] as $key => $label)
                            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" @class(['segment', 'segment-active' => $tab === $key])>
                                {{ $label }} <span class="num font-mono text-[11px] text-slate">{{ $tabCounts[$key] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                @if ($activities->isEmpty())
                    <x-ui.empty icon="inbox" :title="$tabCounts['all'] ? 'No activities in this view' : 'No activities are scheduled for today'">
                        @if ($tabCounts['all'])
                            Choose another tab to see the rest of today's register.
                        @else
                            Activities appear here once definitions that apply to today exist.
                        @endif
                    </x-ui.empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="table-ops">
                            <thead>
                                <tr>
                                    <th scope="col">Activity</th>
                                    <th scope="col" class="w-32">Status</th>
                                    <th scope="col" class="hidden md:table-cell">Latest remark</th>
                                    <th scope="col" class="w-40">Last updated</th>
                                    <th scope="col" class="w-12"><span class="sr-only">Open</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activities as $instance)
                                    <tr wire:key="today-{{ $instance->id }}">
                                        <td>
                                            <p class="mono text-[11.5px] text-slate">{{ $instance->definition->code }} · {{ $instance->scheduled_at ? ops_time($instance->scheduled_at) : 'Any time' }}</p>
                                            <p class="font-medium">{{ $instance->definition->title }}</p>
                                        </td>
                                        <td><x-ui.status :state="$instance->wasReopened() ? 'reopened' : $instance->displayState()" /></td>
                                        <td class="hidden max-w-[26rem] md:table-cell">
                                            <p class="line-clamp-2 text-[12.5px] {{ $instance->latestUpdate ? 'text-ink-soft' : 'italic text-slate' }}">{{ $instance->latestUpdate?->remark ?? 'Awaiting first update' }}</p>
                                        </td>
                                        <td>
                                            @if ($instance->latestUpdate)
                                                <p class="truncate text-[12.5px]">{{ $instance->latestUpdate->updater_name }}</p>
                                                <p class="num font-mono text-[11.5px] text-slate">{{ ops_time($instance->latestUpdate->created_at) }} {{ ops_tz() }}</p>
                                            @else
                                                <p class="text-[12.5px] text-slate">{{ $instance->assignee?->name ?? 'Unassigned' }}</p>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('register', ['activity' => $instance->id]) }}" class="btn btn-ghost btn-sm" aria-label="Open {{ $instance->definition->title }}"><x-lucide-arrow-right class="size-4" /></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="space-y-4">
            {{-- Completion trend --}}
            <section class="panel" aria-labelledby="trend-title">
                <div class="panel-header">
                    <h2 id="trend-title" class="section-title">Last 14 days</h2>
                    @can(Ability::VIEW_MANAGEMENT_REPORTS)
                        <a href="{{ route('reports.show', ['type' => 'trend', 'from' => $today->subDays(13)->format('Y-m-d'), 'to' => $today->format('Y-m-d')]) }}" class="text-[12px] font-medium text-forest hover:underline">Trend report →</a>
                    @endcan
                </div>
                <div class="p-4">
                    <div class="flex h-28 items-end gap-1" role="img" aria-label="Completion at close of day over the last 14 days">
                        @foreach ($trend as $day)
                            @php($height = $day->total ? max(6, round($day->total / $maxTrend * 100)) : 4)
                            <div class="group relative flex h-full flex-1 flex-col justify-end" title="{{ $day->date->format('D d M') }}: {{ $day->total ? $day->done.' of '.$day->total.' done at close ('.$day->rate.'%)' : 'nothing scheduled' }}">
                                <div class="flex w-full flex-col overflow-hidden rounded-[2px]" style="height: {{ $height }}%">
                                    @if ($day->total)
                                        <div class="bg-rule" style="height: {{ round($day->not_updated / $day->total * 100, 2) }}%"></div>
                                        <div class="bg-amber-strong" style="height: {{ round($day->pending / $day->total * 100, 2) }}%"></div>
                                        <div class="flex-1 bg-forest"></div>
                                    @else
                                        <div class="h-full bg-subtle"></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1.5 flex justify-between font-mono text-[10.5px] text-slate">
                        <span>{{ $trend->first()?->date->format('d M') }}</span><span>Today</span>
                    </div>
                    <p class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[11.5px] text-slate">
                        <span class="flex items-center gap-1"><span class="size-2 rounded-[2px] bg-forest"></span> Done at close</span>
                        <span class="flex items-center gap-1"><span class="size-2 rounded-[2px] bg-amber-strong"></span> Pending</span>
                        <span class="flex items-center gap-1"><span class="size-2 rounded-[2px] bg-rule"></span> Not updated</span>
                    </p>
                </div>
            </section>

            {{-- Recent activity --}}
            <section class="panel" aria-labelledby="recent-title">
                <div class="panel-header">
                    <h2 id="recent-title" class="section-title">Recent updates</h2>
                    <a href="{{ route('reports.show', ['type' => 'history', 'basis' => 'recorded', 'order' => 'desc', 'from' => $today->subDays(6)->format('Y-m-d'), 'to' => $today->format('Y-m-d')]) }}" class="text-[12px] font-medium text-forest hover:underline">History →</a>
                </div>
                @if ($recent->isEmpty())
                    <x-ui.empty icon="history" title="No updates recorded yet">Updates appear here as soon as someone records one.</x-ui.empty>
                @else
                    <ol class="space-y-0 p-4">
                        @foreach ($recent as $update)
                            <li class="relative flex gap-3 pb-4 last:pb-0">
                                @unless ($loop->last)<span class="absolute left-[4px] top-3.5 h-full w-px bg-hairline" aria-hidden="true"></span>@endunless
                                <span class="relative mt-1.5 size-[9px] shrink-0 rounded-full {{ $update->new_status->value === 'done' ? 'bg-forest' : 'bg-amber-strong' }}" aria-hidden="true"></span>
                                <div class="min-w-0 text-[12.5px]">
                                    <p class="flex items-center justify-between gap-2">
                                        <span class="num font-mono font-medium">{{ ops_is_today($update->created_at) ? ops_time($update->created_at) : ops_format($update->created_at, 'd M H:i') }}</span>
                                        <a href="{{ route('activities.show', $update->activity_instance_id) }}" class="mono text-[11px] text-slate hover:text-ink hover:underline">{{ $update->instance->definition->code }}</a>
                                    </p>
                                    <p class="mt-0.5 leading-snug"><span class="font-medium">{{ $update->updater_name }}</span> {{ strtolower($update->eventLabel()) }}: {{ $update->instance->definition->title }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</div>
