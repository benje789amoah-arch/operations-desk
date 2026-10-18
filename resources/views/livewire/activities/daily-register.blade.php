@php
    use App\Support\Ability;
    $day = $this->day();
    $counts = $this->counts;
    $completion = $counts['total'] ? round($counts['done'] / $counts['total'] * 100) : 0;
    $selectedInstance = $this->selectedInstance;
@endphp

<div>
    <x-ui.page-header
        :crumbs="['Operations' => route('dashboard'), 'Daily register' => route('register'), $day->format('d M Y')]"
        title="Daily activity register"
        :description="'Every support activity scheduled for '.$day->format('l, j F Y').', with its latest status and remark.'">
        <x-slot:actions>
            @can(Ability::EXPORT_REPORTS)
                <a href="{{ route('reports.export', ['type' => 'daily', 'from' => $day->format('Y-m-d'), 'to' => $day->format('Y-m-d')]) }}" class="btn btn-secondary">
                    <x-lucide-download class="size-4" /> Export CSV
                </a>
            @endcan
            @can(Ability::SCHEDULE_ACTIVITIES)
                <button type="button" wire:click="$toggle('scheduling')" class="btn btn-primary" @disabled($day->lt(\App\Support\OpsClock::today()))
                        title="{{ $day->lt(\App\Support\OpsClock::today()) ? 'Ad-hoc activities can be scheduled for today or later' : 'Add an ad-hoc activity to this date' }}">
                    <x-lucide-calendar-plus class="size-4" /> Schedule activity
                </button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Date navigation --}}
    <div class="panel mb-3 flex flex-wrap items-center gap-2 p-2.5">
        <button type="button" wire:click="shiftDay(-1)" class="btn btn-secondary btn-sm">
            <x-lucide-chevron-left class="size-4" /> <span class="hidden sm:inline">{{ $day->subDay()->format('D d M') }}</span><span class="sm:hidden">Previous</span>
        </button>
        <label class="sr-only" for="register-date">Operational date</label>
        <input id="register-date" type="date" wire:model.live="date" class="input h-8 w-[10.5rem] font-mono text-[12.5px]">
        <button type="button" wire:click="shiftDay(1)" class="btn btn-secondary btn-sm">
            <span class="hidden sm:inline">{{ $day->addDay()->format('D d M') }}</span><span class="sm:hidden">Next</span> <x-lucide-chevron-right class="size-4" />
        </button>
        @unless ($isToday)
            <button type="button" wire:click="goToToday" class="btn btn-ghost btn-sm text-forest"><x-lucide-calendar-check class="size-4" /> Today</button>
        @endunless

        <span class="ml-auto flex items-center gap-1.5 font-mono text-[11.5px] text-slate">
            @if ($isToday)
                <span class="size-1.5 rounded-full bg-forest" aria-hidden="true"></span> Today's register
            @elseif ($isFuture)
                <x-lucide-calendar-clock class="size-3.5" /> Future date
            @else
                <x-lucide-history class="size-3.5" /> Historical date
            @endif
        </span>
    </div>

    @if ($scheduling)
        <form wire:submit="schedule" class="panel mb-3 grid gap-3 p-4 md:grid-cols-[1fr_16rem_auto] md:items-end">
            <div>
                <label for="schedule-definition" class="field-label">Activity to schedule for {{ $day->format('d M Y') }}</label>
                <select id="schedule-definition" wire:model="scheduleDefinition" class="input" @error('scheduleDefinition') aria-invalid="true" @enderror>
                    <option value="">Choose an activity definition…</option>
                    @foreach ($definitions as $definition)
                        <option value="{{ $definition->id }}">{{ $definition->code }} · {{ $definition->title }}</option>
                    @endforeach
                </select>
                <x-ui.error for="scheduleDefinition" />
            </div>
            <div>
                <label for="schedule-assignee" class="field-label">Responsible person</label>
                <select id="schedule-assignee" wire:model="scheduleAssignee" class="input">
                    <option value="">Definition default</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="schedule"><x-lucide-plus class="size-4" /> Add to register</button>
                <button type="button" wire:click="$set('scheduling', false)" class="btn btn-ghost">Cancel</button>
            </div>
        </form>
    @endif

    {{-- Summary --}}
    <div class="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Activities" :value="$counts['total']" icon="list-checks" :hint="$counts['carried_over'] ? $counts['carried_over'].' pending from earlier days' : 'Scheduled for this date'" />
        <x-ui.stat label="Done" :value="$counts['done']" icon="circle-check" tone="positive">
            <div class="mt-1 flex items-center gap-2"><x-ui.progress :value="$completion" label="Completion for the day" class="flex-1" /><span class="num font-mono">{{ $completion }}%</span></div>
        </x-ui.stat>
        <x-ui.stat label="Pending" :value="$counts['pending']" icon="circle-dot" tone="attention" hint="Updated, still outstanding" />
        <x-ui.stat label="Not updated" :value="$counts['not_updated']" icon="circle-dashed" hint="Nobody has reported yet" />
    </div>

    {{-- Filters --}}
    <div class="panel mb-3 flex flex-col gap-2 p-2.5 md:flex-row md:items-center">
        <div class="relative flex-1">
            <x-lucide-search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-slate" />
            <label for="register-search" class="sr-only">Search activities</label>
            <input id="register-search" type="search" wire:model.live.debounce.350ms="search" class="input pl-8" placeholder="Search title, code or latest remark…">
        </div>
        <div class="grid grid-cols-2 gap-2 md:flex">
            <label class="sr-only" for="register-state">Status</label>
            <select id="register-state" wire:model.live="state" class="input md:w-44">
                <option value="">All statuses</option>
                <option value="not_updated">Not updated</option>
                <option value="pending">Pending (updated)</option>
                <option value="follow_up">Flagged for follow-up</option>
                <option value="done">Done</option>
            </select>
            <label class="sr-only" for="register-category">Category</label>
            <select id="register-category" wire:model.live="category" class="input md:w-48">
                <option value="">All categories</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="register-person">Responsible person</label>
            <select id="register-person" wire:model.live="person" class="input md:w-44">
                <option value="">Anyone responsible</option>
                @foreach ($people as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
            @if ($filtersActive)
                <button type="button" wire:click="resetFilters" class="btn btn-ghost" title="Clear all filters"><x-lucide-filter-x class="size-4" /> Reset</button>
            @endif
        </div>
    </div>

    <div @class(['grid gap-4', 'xl:grid-cols-[minmax(0,1fr)_440px]' => $selectedInstance])>
        {{-- Register --}}
        <section class="panel min-w-0 overflow-hidden" aria-label="Activities for {{ $day->format('d M Y') }}">
            <div class="panel-header">
                <h2 class="section-title flex items-center gap-2">
                    <x-lucide-clipboard-list class="size-4 text-slate" /> Register entries
                    <span class="chip chip-neutral num">{{ $instances->total() }}</span>
                </h2>
                <span wire:loading.delay class="meta flex items-center gap-1.5"><x-lucide-loader-circle class="size-3.5 animate-spin" /> Updating…</span>
            </div>

            @if ($instances->isEmpty())
                @if ($isFuture)
                    <x-ui.empty icon="calendar-clock" title="Activities for this date are created on the day">
                        {{ $scheduledForFuture }} active definition{{ $scheduledForFuture === 1 ? ' is' : 's are' }} scheduled to run on {{ $day->format('l, j F') }}. Instances appear here from 00:01 {{ ops_tz() }} that day.
                    </x-ui.empty>
                @elseif ($filtersActive)
                    <x-ui.empty icon="search" title="No activities match these filters">
                        Try a different search or clear the filters to see the whole register.
                        <x-slot:action><button type="button" wire:click="resetFilters" class="btn btn-secondary btn-sm"><x-lucide-filter-x class="size-4" /> Clear filters</button></x-slot:action>
                    </x-ui.empty>
                @else
                    <x-ui.empty icon="inbox" title="No activities were scheduled for this date">
                        @can(Ability::MANAGE_DEFINITIONS)
                            Create an activity definition to start building the daily register.
                            <x-slot:action><a href="{{ route('definitions.create') }}" class="btn btn-primary btn-sm"><x-lucide-plus class="size-4" /> New definition</a></x-slot:action>
                        @else
                            Nothing was on the register for {{ $day->format('j F Y') }}.
                        @endcan
                    </x-ui.empty>
                @endif
            @else
                {{-- Desktop / tablet table --}}
                <div class="hidden overflow-x-auto md:block">
                    <table class="table-ops">
                        <thead>
                            <tr>
                                <th scope="col" class="w-28">Ref · time</th>
                                <th scope="col">Activity</th>
                                <th scope="col" class="w-32">Status</th>
                                <th scope="col" class="hidden lg:table-cell">Latest remark</th>
                                <th scope="col" class="w-40">Last update</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($instances as $instance)
                                @php($isSelected = $selected === $instance->id)
                                <tr wire:key="row-{{ $instance->id }}" @class(['cursor-pointer', 'bg-forest-tint/50' => $isSelected]) wire:click="select({{ $instance->id }})">
                                    <td>
                                        <span @class(['mono block text-[12.5px] font-semibold', 'text-amber' => $instance->attentionReasons() !== [], 'text-ink' => $instance->attentionReasons() === []])>{{ $instance->definition->code }}</span>
                                        <span class="num font-mono text-[11.5px] text-slate">{{ $instance->scheduled_at ? ops_time($instance->scheduled_at) : 'Any time' }}</span>
                                    </td>
                                    <td>
                                        <button type="button" wire:click.stop="select({{ $instance->id }})" class="text-left font-medium text-ink hover:underline focus-visible:underline" aria-label="Open {{ $instance->definition->title }}">
                                            {{ $instance->definition->title }}
                                        </button>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11.5px] text-slate">
                                            @if ($instance->definition->category)<span>{{ $instance->definition->category->name }}</span><span aria-hidden="true">·</span>@endif
                                            <span class="flex items-center gap-1"><x-lucide-user class="size-3" /> {{ $instance->assignee?->name ?? 'Unassigned' }}</span>
                                            <x-ui.priority :priority="$instance->definition->priority" quiet />
                                            @if ($instance->requires_follow_up)<span class="chip chip-pending"><x-lucide-flag class="size-3" /> Follow-up</span>@endif
                                        </div>
                                    </td>
                                    <td><x-ui.status :state="$instance->wasReopened() ? 'reopened' : $instance->displayState()" /></td>
                                    <td class="hidden max-w-[22rem] lg:table-cell">
                                        @if ($instance->latestUpdate)
                                            <p class="line-clamp-2 text-[12.5px] leading-relaxed text-ink-soft">{{ $instance->latestUpdate->remark }}</p>
                                        @else
                                            <p class="text-[12.5px] italic text-slate">Awaiting first update</p>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($instance->latestUpdate)
                                            <p class="truncate text-[12.5px] font-medium">{{ $instance->latestUpdate->updater_name }}</p>
                                            <p class="num font-mono text-[11.5px] text-slate">{{ ops_is_today($instance->latestUpdate->created_at) ? ops_time($instance->latestUpdate->created_at) : ops_format($instance->latestUpdate->created_at, 'd M H:i') }} {{ ops_tz() }}</p>
                                        @else
                                            <p class="text-[12.5px] text-slate">—</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile list --}}
                <ul class="divide-y divide-hairline md:hidden">
                    @foreach ($instances as $instance)
                        <li wire:key="m-row-{{ $instance->id }}">
                            <button type="button" wire:click="select({{ $instance->id }})" class="block w-full px-4 py-3.5 text-left active:bg-wash">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="mono text-[11.5px] font-semibold text-slate">{{ $instance->definition->code }} · {{ $instance->scheduled_at ? ops_time($instance->scheduled_at) : 'Any time' }}</p>
                                        <p class="mt-0.5 font-medium leading-snug">{{ $instance->definition->title }}</p>
                                    </div>
                                    <x-ui.status :state="$instance->wasReopened() ? 'reopened' : $instance->displayState()" class="shrink-0" />
                                </div>
                                @if ($instance->latestUpdate)
                                    <p class="mt-2 line-clamp-2 text-[12.5px] text-ink-soft">{{ $instance->latestUpdate->remark }}</p>
                                    <p class="mt-1 text-[11.5px] text-slate">{{ $instance->latestUpdate->updater_name }} · <span class="font-mono">{{ ops_time($instance->latestUpdate->created_at) }}</span></p>
                                @else
                                    <p class="mt-2 text-[12px] italic text-slate">Awaiting first update · {{ $instance->assignee?->name ?? 'Unassigned' }}</p>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                {{ $instances->links() }}
            @endif
        </section>

        {{-- Detail and update panel --}}
        @if ($selectedInstance)
            <div class="fixed inset-0 z-40 bg-ink/30 xl:hidden" wire:click="closePanel" aria-hidden="true"></div>
            <aside wire:key="panel-{{ $selectedInstance->id }}"
                   class="fixed inset-y-0 right-0 z-50 w-full max-w-[440px] overflow-y-auto border-l border-hairline bg-surface shadow-[var(--shadow-sheet)] animate-slide-in xl:static xl:z-auto xl:max-w-none xl:self-start xl:rounded-lg xl:border xl:shadow-none"
                   aria-label="Activity detail" x-data x-init="$el.focus()" tabindex="-1" @keydown.escape="$wire.closePanel()">
                <div class="sticky top-0 z-10 border-b border-hairline bg-[#f3f5f2] px-4 py-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if ($selectedInstance->attentionReasons() !== [])<span class="chip chip-pending">Needs attention</span>@endif
                                <span class="mono text-[11.5px] text-slate">{{ $selectedInstance->reference() }}</span>
                            </div>
                            <h2 class="mt-1 text-[16px] font-semibold leading-snug">{{ $selectedInstance->definition->title }}</h2>
                        </div>
                        <button type="button" wire:click="closePanel" class="btn btn-ghost btn-sm -mr-1" aria-label="Close activity panel"><x-lucide-x class="size-4" /></button>
                    </div>
                    <dl class="mt-2.5 grid grid-cols-2 gap-x-3 gap-y-1.5 text-[12px]">
                        <div><dt class="text-slate">Operational date</dt><dd class="font-mono">{{ $selectedInstance->operational_date->format('D d M Y') }}</dd></div>
                        <div><dt class="text-slate">Responsible</dt><dd>{{ $selectedInstance->assignee?->name ?? 'Unassigned' }}</dd></div>
                        <div><dt class="text-slate">Schedule</dt><dd class="font-mono">{{ $selectedInstance->scheduled_at ? ops_time($selectedInstance->scheduled_at) : 'Any time' }}@if ($selectedInstance->due_at) · due {{ ops_time($selectedInstance->due_at) }}@endif</dd></div>
                        <div><dt class="text-slate">Priority</dt><dd><x-ui.priority :priority="$selectedInstance->definition->priority" /></dd></div>
                    </dl>
                    @if ($reasons = $selectedInstance->attentionReasons())
                        <p class="mt-2 flex flex-wrap gap-1">@foreach ($reasons as $reason)<span class="chip chip-pending">{{ $reason }}</span>@endforeach</p>
                    @endif
                </div>

                <div class="space-y-5 p-4">
                    @if ($selectedInstance->definition->description)
                        <details class="group rounded-md border border-hairline">
                            <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-[12.5px] font-medium">
                                <span class="flex items-center gap-1.5"><x-lucide-book-open class="size-4 text-slate" /> Procedure for this check</span>
                                <x-lucide-chevron-down class="size-4 text-slate transition-transform group-open:rotate-180" />
                            </summary>
                            <p class="whitespace-pre-line border-t border-hairline px-3 py-2.5 text-[12.5px] leading-relaxed text-ink-soft">{{ $selectedInstance->definition->description }}</p>
                        </details>
                    @endif

                    <livewire:activities.record-update-form :instance="$selectedInstance" :compact="true" :key="'update-form-'.$selectedInstance->id" />

                    <section>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="eyebrow">History for this activity</h3>
                            <a href="{{ route('activities.show', $selectedInstance) }}" class="flex items-center gap-1 text-[12px] font-medium text-forest hover:underline">Full record <x-lucide-arrow-up-right class="size-3.5" /></a>
                        </div>
                        <x-activity.timeline :events="$timeline->take(-6)" />
                    </section>
                </div>
            </aside>
        @endif
    </div>
</div>
