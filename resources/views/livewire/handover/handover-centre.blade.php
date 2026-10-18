@php
    use App\Support\Ability;
    $user = auth()->user();
    $printParams = array_filter(['date' => $day->format('Y-m-d'), 'shift' => $shift ?: null, 'person' => $person ?: null]);
@endphp

<div>
    <x-ui.page-header
        :crumbs="['Operations', 'Handover centre', $day->format('d M Y')]"
        title="Handover centre"
        description="What the next person on the desk needs to know: outstanding work with its latest explanation, what was completed, and who did what.">
        <x-slot:actions>
            <a href="{{ route('handover.print', $printParams) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-lucide-printer class="size-4" /> Printable summary</a>
            <a href="{{ route('register', ['date' => $day->format('Y-m-d')]) }}" class="btn btn-primary"><x-lucide-list-checks class="size-4" /> Open register</a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Period selection --}}
    <div class="panel mb-4 flex flex-wrap items-center gap-2 p-2.5">
        <button type="button" wire:click="shiftDay(-1)" class="btn btn-secondary btn-sm" aria-label="Previous day"><x-lucide-chevron-left class="size-4" /></button>
        <label for="handover-date" class="sr-only">Operational date</label>
        <input id="handover-date" type="date" wire:model.live="date" class="input h-8 w-[10.5rem] font-mono text-[12.5px]">
        <button type="button" wire:click="shiftDay(1)" class="btn btn-secondary btn-sm" aria-label="Next day"><x-lucide-chevron-right class="size-4" /></button>
        @unless ($isToday)
            <button type="button" wire:click="goToToday" class="btn btn-ghost btn-sm text-forest">Today</button>
        @endunless
        <div class="ml-auto grid w-full grid-cols-2 gap-2 sm:w-auto sm:flex">
            <label for="handover-person" class="sr-only">Personnel</label>
            <select id="handover-person" wire:model.live="person" class="input h-8 text-[12.5px] sm:w-48">
                <option value="">All personnel</option>
                @foreach ($people as $p)<option value="{{ $p->id }}">{{ $p->name }}{{ $p->is_active ? '' : ' (inactive)' }}</option>@endforeach
            </select>
            <label for="handover-shift" class="sr-only">Journal period</label>
            <select id="handover-shift" wire:model.live="shift" class="input h-8 text-[12.5px] sm:w-44">
                <option value="">Whole day</option>
                @foreach ($shifts as $key => $s)<option value="{{ $key }}">{{ $s['label'] }} ({{ $s['start'] }}–{{ $s['end'] }})</option>@endforeach
            </select>
        </div>
    </div>

    {{-- Briefing banner --}}
    @php($outstandingCount = $outstanding->count())
    <section @class(['panel mb-4 border-l-4 p-4', 'border-l-amber-strong' => $outstandingCount, 'border-l-forest' => ! $outstandingCount]) aria-labelledby="briefing-title">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="eyebrow">Handover briefing · {{ $day->format('l, j F Y') }}</p>
                <h2 id="briefing-title" class="mt-1 text-[18px] font-semibold tracking-tight">
                    @if ($outstandingCount)
                        {{ $outstandingCount }} item{{ $outstandingCount === 1 ? '' : 's' }} to carry forward
                    @elseif ($counts['total'])
                        Everything on this day's register is done
                    @else
                        Nothing was scheduled for this day
                    @endif
                </h2>
                <p class="mt-1 text-[13px] text-slate">
                    {{ $counts['done'] }} of {{ $counts['total'] }} completed · {{ $counts['updates'] }} update{{ $counts['updates'] === 1 ? '' : 's' }} recorded this day
                    @if ($counts['carried_over']) · {{ $counts['carried_over'] }} still pending from earlier days @endif
                    @if ($person) · filtered to {{ $people->firstWhere('id', (int) $person)?->name }} @endif
                </p>
            </div>
            <dl class="grid grid-cols-4 gap-2 text-center sm:gap-3">
                @foreach ([['Done', $counts['done'], 'text-forest'], ['Pending', $counts['pending'], 'text-amber'], ['Not updated', $counts['not_updated'], 'text-ink'], ['Carried', $counts['carried_over'], 'text-amber']] as [$label, $value, $tone])
                    <div class="min-w-[4.5rem] rounded-md border border-hairline bg-canvas px-2 py-2">
                        <dt class="text-[11px] text-slate">{{ $label }}</dt>
                        <dd class="num text-[20px] font-semibold {{ $tone }}">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        <p class="mt-3 border-t border-hairline pt-2.5 text-[12px] text-slate">Statuses show the current state. Each submitted handover below keeps a frozen snapshot of the state at the moment it was submitted.</p>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="min-w-0 space-y-4">
            {{-- Outstanding --}}
            <section class="panel" aria-labelledby="outstanding-title">
                <div class="panel-header">
                    <h2 id="outstanding-title" class="section-title flex items-center gap-2"><x-lucide-flag class="size-4 text-amber" /> Outstanding &amp; pending activities</h2>
                    <p class="meta">Flagged first, then by priority, reopened, oldest date</p>
                </div>
                @if ($outstanding->isEmpty())
                    <x-ui.empty icon="badge-check" title="No outstanding activities">
                        {{ $isFuture ? 'This date has not started yet.' : 'There is nothing pending to hand over for this day.' }}
                    </x-ui.empty>
                @else
                    <ul class="divide-y divide-hairline">
                        @foreach ($outstanding as $item)
                            @php($history = $item->updates->sortByDesc('id')->values())
                            <li wire:key="out-{{ $item->id }}" class="p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="mono text-[12px] font-semibold">{{ $item->definition->code }}</span>
                                            <x-ui.status :state="$item->wasReopened() ? 'reopened' : $item->displayState()" />
                                            <x-ui.priority :priority="$item->definition->priority" quiet />
                                            @foreach ($item->attentionReasons($day) as $reason)
                                                @continue($reason === 'Critical priority' || $reason === 'Reopened')
                                                <span class="chip chip-pending">{{ $reason }}</span>
                                            @endforeach
                                        </div>
                                        <h3 class="mt-1 font-medium">{{ $item->definition->title }}</h3>
                                        <p class="mt-0.5 text-[12px] text-slate">
                                            {{ $item->operational_date->format('D d M') }} · Responsible: <span class="text-ink-soft">{{ $item->assignee?->name ?? 'Unassigned' }}</span>
                                            @if ($item->due_at) · due <span class="font-mono">{{ ops_time($item->due_at) }}</span>@endif
                                        </p>
                                    </div>
                                    <a href="{{ route('register', ['date' => $item->operational_date->format('Y-m-d'), 'activity' => $item->id]) }}" class="btn btn-secondary btn-sm shrink-0 self-start"><x-lucide-square-pen class="size-4" /> Update</a>
                                </div>

                                @if ($history->isNotEmpty())
                                    @php($latest = $history->first())
                                    <div class="mt-3 rounded-md border border-hairline bg-canvas p-3">
                                        <p class="flex flex-wrap items-center justify-between gap-2 text-[11.5px] text-slate">
                                            <span class="eyebrow text-[10px]">Latest remark</span>
                                            <span><span class="font-medium text-ink-soft">{{ $latest->updater_name }}</span>@if ($latest->updater_designation) ({{ $latest->updater_designation }})@endif · <span class="font-mono">{{ ops_datetime($latest->created_at) }}</span></span>
                                        </p>
                                        <p class="mt-1.5 whitespace-pre-line text-[13px] leading-relaxed">{{ $latest->remark }}</p>
                                        @if ($latest->supporting_details)<p class="mt-1.5 font-mono text-[11.5px] text-slate">{{ $latest->supporting_details }}</p>@endif
                                    </div>
                                    @if ($history->count() > 1)
                                        <details class="group mt-2">
                                            <summary class="cursor-pointer list-none text-[12px] font-medium text-forest hover:underline">
                                                <span class="group-open:hidden">Show {{ $history->count() - 1 }} earlier update{{ $history->count() - 1 === 1 ? '' : 's' }}</span>
                                                <span class="hidden group-open:inline">Hide earlier updates</span>
                                            </summary>
                                            <ol class="mt-2 space-y-2 border-l-2 border-hairline pl-3">
                                                @foreach ($history->slice(1) as $earlier)
                                                    <li class="text-[12.5px]">
                                                        <p class="text-slate"><span class="font-mono">{{ ops_datetime($earlier->created_at) }}</span> · {{ $earlier->updater_name }} · {{ $earlier->eventLabel() }}</p>
                                                        <p class="text-ink-soft">{{ $earlier->remark }}</p>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </details>
                                    @endif
                                @else
                                    <p class="mt-3 rounded-md border border-dashed border-hairline p-3 text-[12.5px] text-slate">Nobody has recorded an update on this activity yet. Confirm whether it was started before taking it over.</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Journal --}}
            <section class="panel" aria-labelledby="journal-title">
                <div class="panel-header">
                    <h2 id="journal-title" class="section-title flex items-center gap-2"><x-lucide-history class="size-4 text-slate" /> Update journal</h2>
                    <p class="meta">{{ $shift ? $shifts[$shift]['label'].' ('.$shifts[$shift]['start'].'–'.$shifts[$shift]['end'].')' : 'Whole day' }} · newest first · {{ $journal->count() >= $journalLimit ? 'latest '.$journalLimit.' shown — use the history report for the full list' : $journal->count().' update'.($journal->count() === 1 ? '' : 's') }}</p>
                </div>
                @if ($journal->isEmpty())
                    <x-ui.empty icon="history" title="No activity updates were recorded for this period" />
                @else
                    <div class="overflow-x-auto">
                        <table class="table-ops">
                            <thead><tr><th scope="col" class="w-24">Time</th><th scope="col">Activity</th><th scope="col" class="w-44">Change</th><th scope="col" class="hidden md:table-cell">Remark</th><th scope="col" class="w-36">By</th></tr></thead>
                            <tbody>
                                @foreach ($journal as $entry)
                                    <tr wire:key="journal-{{ $entry->id }}">
                                        <td class="num whitespace-nowrap font-mono text-[12px]">{{ ops_time($entry->created_at, true) }}</td>
                                        <td>
                                            <a href="{{ route('activities.show', $entry->activity_instance_id) }}" class="mono text-[11.5px] text-slate hover:underline">{{ $entry->instance->reference() }}</a>
                                            <p class="text-[12.5px] font-medium">{{ $entry->instance->definition->title }}</p>
                                        </td>
                                        <td class="whitespace-nowrap"><span class="flex items-center gap-1"><x-ui.status :state="$entry->previous_status?->value ?? 'not_updated'" /><x-lucide-arrow-right class="size-3 text-slate" /><x-ui.status :state="$entry->isReopening() ? 'reopened' : $entry->new_status" /></span></td>
                                        <td class="hidden max-w-sm md:table-cell"><p class="line-clamp-2 text-[12.5px] text-ink-soft">{{ $entry->remark }}</p></td>
                                        <td class="text-[12.5px]">{{ $entry->updater_name }}<span class="block text-[11px] text-slate">{{ $entry->updater_staff_number }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Completed --}}
            <section class="panel" aria-labelledby="completed-title">
                <div class="panel-header">
                    <h2 id="completed-title" class="section-title flex items-center gap-2"><x-lucide-circle-check class="size-4 text-forest" /> Completed this day <span class="chip chip-done num">{{ $completed->count() }}</span></h2>
                </div>
                @if ($completed->isEmpty())
                    <x-ui.empty icon="circle-dashed" title="No activities completed for this day yet" />
                @else
                    <div class="overflow-x-auto">
                        <table class="table-ops">
                            <thead><tr><th scope="col" class="w-24">Code</th><th scope="col">Activity</th><th scope="col">Completed by</th><th scope="col" class="w-24">Time</th><th scope="col" class="hidden lg:table-cell">Remark</th></tr></thead>
                            <tbody>
                                @foreach ($completed as $item)
                                    <tr wire:key="done-{{ $item->id }}">
                                        <td class="mono text-[12px] font-semibold">{{ $item->definition->code }}</td>
                                        <td class="font-medium">{{ $item->definition->title }}</td>
                                        <td class="text-[12.5px]">{{ $item->latestUpdate?->updater_name }}</td>
                                        <td class="num font-mono text-[12px]">{{ ops_time($item->latestUpdate?->created_at) }}</td>
                                        <td class="hidden max-w-sm lg:table-cell"><p class="line-clamp-2 text-[12.5px] text-ink-soft">{{ $item->latestUpdate?->remark }}</p></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        {{-- Sign-off --}}
        <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
            <section class="panel" aria-labelledby="signoff-title">
                <div class="panel-header"><h2 id="signoff-title" class="section-title flex items-center gap-2"><x-lucide-clipboard-check class="size-4 text-slate" /> Shift handovers</h2></div>

                @if ($handovers->isEmpty())
                    <p class="px-4 pt-4 text-[12.5px] text-slate">No handover has been submitted for {{ $day->format('d M Y') }} yet.</p>
                @else
                    <ul class="divide-y divide-hairline">
                        @foreach ($handovers as $handover)
                            <li wire:key="handover-{{ $handover->id }}" class="space-y-2 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[13px] font-medium">{{ $handover->shiftLabel() }}</p>
                                    @if ($handover->isAcknowledged())
                                        <span class="chip chip-done"><x-lucide-check class="size-3" /> Acknowledged</span>
                                    @else
                                        <span class="chip chip-pending"><x-lucide-clock class="size-3" /> Awaiting acknowledgement</span>
                                    @endif
                                </div>
                                <p class="text-[12px] text-slate">
                                    Submitted by <span class="font-medium text-ink-soft">{{ $handover->outgoingUser->name }}</span> at <span class="font-mono">{{ ops_time($handover->submitted_at) }}</span>
                                    · {{ $handover->snapshot['counts']['outstanding'] ?? 0 }} outstanding at submission
                                </p>
                                <blockquote class="whitespace-pre-line rounded border-l-2 border-hairline bg-canvas px-3 py-2 text-[12.5px] leading-relaxed">{{ $handover->outgoing_note }}</blockquote>
                                @if ($handover->isAcknowledged())
                                    <p class="text-[12px] text-slate">Acknowledged by <span class="font-medium text-ink-soft">{{ $handover->incomingUser?->name }}</span> at <span class="font-mono">{{ ops_time($handover->acknowledged_at) }}</span></p>
                                    @if ($handover->acknowledgement_note)<p class="text-[12.5px] italic text-ink-soft">“{{ $handover->acknowledgement_note }}”</p>@endif
                                @elseif ($user->can('acknowledge', $handover))
                                    <form wire:submit="acknowledge({{ $handover->id }})" class="space-y-2 pt-1">
                                        <label for="ack-{{ $handover->id }}" class="field-label">Acknowledgement note <span class="font-normal text-slate">— optional</span></label>
                                        <textarea id="ack-{{ $handover->id }}" wire:model="acknowledgementNotes.{{ $handover->id }}" rows="2" class="input" placeholder="e.g. Reviewed outstanding items with the outgoing lead."></textarea>
                                        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" wire:target="acknowledge({{ $handover->id }})"
                                                wire:confirm="Acknowledge this handover? You confirm you have reviewed the outstanding items and are taking them over.">
                                            <x-lucide-handshake class="size-4" /> Acknowledge &amp; take over
                                        </button>
                                    </form>
                                @elseif ($handover->outgoing_user_id === $user->id)
                                    <p class="text-[12px] text-slate">Waiting for the incoming person to acknowledge.</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can(Ability::SUBMIT_HANDOVER)
                    @unless ($isFuture)
                        <form wire:submit="submit" class="space-y-3 border-t border-hairline p-4">
                            <h3 class="text-[13px] font-medium">Submit your handover</h3>
                            <div>
                                <label for="handover-shift-select" class="field-label">Shift ending</label>
                                <select id="handover-shift-select" wire:model="handoverShift" class="input">
                                    @foreach ($shifts as $key => $s)<option value="{{ $key }}" @disabled($handovers->contains('shift', $key))>{{ $s['label'] }} ({{ $s['start'] }}–{{ $s['end'] }}){{ $handovers->contains('shift', $key) ? ' — submitted' : '' }}</option>@endforeach
                                </select>
                                <x-ui.error for="handoverShift" />
                            </div>
                            <div>
                                <label for="handover-note" class="field-label">Note for the incoming person</label>
                                <textarea id="handover-note" wire:model="handoverNote" rows="4" maxlength="3000" class="input" placeholder="What to watch, chase or finish. The outstanding list is attached automatically." @error('handoverNote') aria-invalid="true" @enderror></textarea>
                                <x-ui.error for="handoverNote" />
                                <x-ui.error for="handoverDate" />
                            </div>
                            <p class="text-[12px] text-slate">Submitting records you as the outgoing person, with a snapshot of the {{ $counts['outstanding'] }} outstanding item{{ $counts['outstanding'] === 1 ? '' : 's' }} as they are now.</p>
                            <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" wire:target="submit">
                                <span wire:loading.remove wire:target="submit" class="flex items-center gap-2"><x-lucide-send class="size-4" /> Submit handover</span>
                                <span wire:loading wire:target="submit" class="flex items-center gap-2"><x-lucide-loader-circle class="size-4 animate-spin" /> Submitting…</span>
                            </button>
                        </form>
                    @endunless
                @endcan
            </section>
        </aside>
    </div>
</div>
