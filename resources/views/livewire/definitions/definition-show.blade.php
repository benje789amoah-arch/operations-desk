<div>
    <x-ui.page-header
        :crumbs="['Operations', 'Activity definitions' => route('definitions.index'), $definition->code]"
        :title="$definition->title">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="mono text-[12.5px] font-semibold">{{ $definition->code }}</span>
                <x-ui.priority :priority="$definition->priority" />
                @if ($definition->isArchived())
                    <span class="chip chip-neutral"><x-lucide-archive class="size-3" /> Archived {{ ops_format($definition->archived_at, 'd M Y') }}</span>
                @else
                    <span class="chip chip-done"><x-lucide-circle-check class="size-3" /> Active</span>
                @endif
                <span class="text-[12.5px] text-slate">{{ $definition->category?->name ?? 'Uncategorised' }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $definition)
                <button type="button" wire:click="toggleArchived" class="btn btn-secondary"
                        wire:confirm="{{ $definition->isArchived() ? 'Restore this definition? It will be scheduled again from today.' : 'Archive this definition? No new register entries will be created. Existing records and their history are kept.' }}">
                    <x-dynamic-component :component="$definition->isArchived() ? 'lucide-archive-restore' : 'lucide-archive'" class="size-4" /> {{ $definition->isArchived() ? 'Restore' : 'Archive' }}
                </button>
                <a href="{{ route('definitions.edit', $definition) }}" class="btn btn-primary"><x-lucide-pencil class="size-4" /> Edit</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-4">
            <section class="panel">
                <div class="panel-header"><h2 class="section-title">Definition</h2></div>
                <dl class="grid gap-x-6 gap-y-3 p-4 text-[13px] sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="meta">Recurrence</dt><dd class="mt-0.5">{{ $definition->recurrence->describe($definition->recurrence_days ?? []) }}</dd></div>
                    <div><dt class="meta">Scheduled / due</dt><dd class="mt-0.5 font-mono">{{ $definition->scheduled_time ? substr($definition->scheduled_time, 0, 5) : 'Any time' }} → {{ $definition->due_time ? substr($definition->due_time, 0, 5) : 'no deadline' }} {{ ops_tz() }}</dd></div>
                    <div><dt class="meta">Default responsible</dt><dd class="mt-0.5">{{ $definition->defaultAssignee?->name ?? 'Unassigned' }}</dd></div>
                    <div><dt class="meta">Active from</dt><dd class="mt-0.5 font-mono">{{ $definition->starts_on->format('d M Y') }}{{ $definition->ends_on ? ' to '.$definition->ends_on->format('d M Y') : '' }}</dd></div>
                    <div><dt class="meta">Created</dt><dd class="mt-0.5">{{ ops_format($definition->created_at, 'd M Y') }} by {{ $definition->creator?->name }}</dd></div>
                    <div><dt class="meta">Last changed</dt><dd class="mt-0.5">{{ ops_format($definition->updated_at, 'd M Y H:i') }} by {{ $definition->editor?->name ?? '—' }}</dd></div>
                </dl>
                <div class="border-t border-hairline p-4">
                    <h3 class="eyebrow mb-1.5">Procedure</h3>
                    <p class="max-w-3xl whitespace-pre-line text-[13px] leading-relaxed text-ink-soft">{{ $definition->description ?: 'No procedure has been written for this activity yet.' }}</p>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="panel-header">
                    <h2 class="section-title">Recent register entries</h2>
                    <a href="{{ route('reports.show', ['type' => 'daily', 'definition' => $definition->id, 'from' => \App\Support\OpsClock::today()->subDays(29)->format('Y-m-d'), 'to' => \App\Support\OpsClock::today()->format('Y-m-d')]) }}" class="text-[12px] font-medium text-forest hover:underline">30-day report →</a>
                </div>
                @if ($recent->isEmpty())
                    <x-ui.empty icon="calendar" title="No register entries yet">Entries appear from the first operational date on which this definition is due.</x-ui.empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="table-ops">
                            <thead><tr><th scope="col">Date</th><th scope="col">Status</th><th scope="col" class="hidden md:table-cell">Latest remark</th><th scope="col">Last update</th></tr></thead>
                            <tbody>
                                @foreach ($recent as $instance)
                                    <tr wire:key="instance-{{ $instance->id }}">
                                        <td class="whitespace-nowrap"><a href="{{ route('activities.show', $instance) }}" class="font-mono text-[12.5px] hover:underline">{{ $instance->operational_date->format('D d M') }}</a></td>
                                        <td><x-ui.status :state="$instance->wasReopened() ? 'reopened' : $instance->displayState()" /></td>
                                        <td class="hidden max-w-md md:table-cell"><p class="line-clamp-2 text-[12.5px] text-ink-soft">{{ $instance->latestUpdate?->remark ?? '—' }}</p></td>
                                        <td class="whitespace-nowrap text-[12.5px]">{{ $instance->latestUpdate?->updater_name ?? '—' }}<span class="block font-mono text-[11.5px] text-slate">{{ $instance->latestUpdate ? ops_format($instance->latestUpdate->created_at, 'd M H:i') : '' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-4">
            <section class="panel p-4">
                <h2 class="section-title">All-time record</h2>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-md bg-canvas p-2"><dt class="meta">Entries</dt><dd class="num text-[20px] font-semibold">{{ (int) $stats->total }}</dd></div>
                    <div class="rounded-md bg-forest-tint p-2"><dt class="meta">Done</dt><dd class="num text-[20px] font-semibold text-forest">{{ (int) $stats->done }}</dd></div>
                    <div class="rounded-md bg-amber-tint p-2"><dt class="meta">Pending</dt><dd class="num text-[20px] font-semibold text-amber">{{ (int) $stats->pending }}</dd></div>
                </dl>
            </section>

            <section class="panel p-4">
                <h2 class="section-title">Next scheduled dates</h2>
                @if ($upcoming->isEmpty())
                    <p class="mt-2 text-[12.5px] text-slate">{{ $definition->isArchived() ? 'Archived definitions are not scheduled.' : ($definition->recurrence->value === 'adhoc' ? 'Ad-hoc: scheduled manually from the daily register.' : 'Not due in the next two weeks.') }}</p>
                @else
                    <ul class="mt-2 space-y-1 font-mono text-[12.5px]">
                        @foreach ($upcoming as $date)<li class="flex justify-between"><span>{{ $date->format('D d M Y') }}</span>@if ($loop->first && $date->isToday())<span class="chip chip-done">Today</span>@endif</li>@endforeach
                    </ul>
                @endif
            </section>

            <section class="panel p-4">
                <h2 class="section-title">Change history</h2>
                @if ($changes->isEmpty())
                    <p class="mt-2 text-[12.5px] text-slate">No recorded changes.</p>
                @else
                    <ol class="mt-3 space-y-3">
                        @foreach ($changes as $change)
                            <li class="text-[12.5px]">
                                <p><span class="font-medium">{{ $change->actor_name }}</span> <span class="text-slate">· {{ ops_datetime($change->created_at) }}</span></p>
                                <p class="text-ink-soft">{{ $change->description }}</p>
                                @foreach (array_slice($change->changes(), 0, 4) as $diff)
                                    <p class="mt-0.5 font-mono text-[11px] text-slate">{{ $diff['field'] }}: <span class="line-through">{{ \Illuminate\Support\Str::limit(is_scalar($diff['before']) ? (string) $diff['before'] : json_encode($diff['before']), 40) ?: '∅' }}</span> → <span class="text-ink">{{ \Illuminate\Support\Str::limit(is_scalar($diff['after']) ? (string) $diff['after'] : json_encode($diff['after']), 40) ?: '∅' }}</span></p>
                                @endforeach
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>
</div>
