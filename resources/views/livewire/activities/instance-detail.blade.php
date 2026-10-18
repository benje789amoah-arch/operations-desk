@php($definition = $instance->definition)

<div>
    <x-ui.page-header
        :crumbs="['Operations' => route('dashboard'), 'Daily register' => route('register', ['date' => $instance->operational_date->format('Y-m-d')]), $instance->reference()]"
        :title="$definition->title">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-ui.status :state="$instance->wasReopened() ? 'reopened' : $instance->displayState()" />
                <x-ui.priority :priority="$definition->priority" />
                @foreach ($instance->attentionReasons() as $reason)
                    <span class="chip chip-pending">{{ $reason }}</span>
                @endforeach
                <span class="mono text-[12px] text-slate">{{ $instance->reference() }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('register', ['date' => $instance->operational_date->format('Y-m-d')]) }}" class="btn btn-secondary"><x-lucide-arrow-left-right class="size-4" /> Back to register</a>
            <a href="{{ route('definitions.show', $definition) }}" class="btn btn-ghost"><x-lucide-workflow class="size-4" /> Definition</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-4">
            <section class="panel">
                <div class="panel-header"><h2 class="section-title">Activity details</h2></div>
                <dl class="grid gap-x-6 gap-y-3 p-4 text-[13px] sm:grid-cols-2 xl:grid-cols-3">
                    <div><dt class="meta">Operational date</dt><dd class="mt-0.5 font-mono">{{ $instance->operational_date->format('l, d M Y') }}</dd></div>
                    <div><dt class="meta">Category</dt><dd class="mt-0.5">{{ $definition->category?->name ?? 'Uncategorised' }}</dd></div>
                    <div><dt class="meta">Recurrence</dt><dd class="mt-0.5">{{ $definition->scheduleLabel() }}</dd></div>
                    <div><dt class="meta">Scheduled time</dt><dd class="mt-0.5 font-mono">{{ $instance->scheduled_at ? ops_time($instance->scheduled_at).' '.ops_tz() : 'Any time during the day' }}</dd></div>
                    <div><dt class="meta">Due by</dt><dd class="mt-0.5 font-mono">{{ $instance->due_at ? ops_time($instance->due_at).' '.ops_tz() : 'No deadline' }}</dd></div>
                    <div><dt class="meta">Added to register</dt><dd class="mt-0.5">{{ ops_datetime($instance->created_at) }} · {{ $instance->origin === 'manual' ? 'by '.($instance->creator?->name ?? 'unknown') : 'by scheduler' }}</dd></div>
                    <div class="sm:col-span-2 xl:col-span-3">
                        <dt class="meta">Responsible person</dt>
                        <dd class="mt-1">
                            @can('assign', $instance)
                                <form wire:submit="reassign" class="flex max-w-md gap-2">
                                    <label for="assignee" class="sr-only">Responsible person</label>
                                    <select id="assignee" wire:model="assignee" class="input">
                                        <option value="">Unassigned</option>
                                        @foreach ($people as $person)
                                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="reassign">Reassign</button>
                                </form>
                                <x-ui.error for="assignee" />
                            @else
                                {{ $instance->assignee?->name ?? 'Unassigned' }}
                            @endcan
                        </dd>
                    </div>
                </dl>
                @if ($definition->description)
                    <div class="border-t border-hairline p-4">
                        <h3 class="eyebrow mb-1.5">Procedure</h3>
                        <p class="max-w-3xl whitespace-pre-line text-[13px] leading-relaxed text-ink-soft">{{ $definition->description }}</p>
                    </div>
                @endif
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="section-title flex items-center gap-2"><x-lucide-history class="size-4 text-slate" /> Activity history &amp; audit trail</h2>
                    <span class="meta">{{ $instance->updates->count() }} update{{ $instance->updates->count() === 1 ? '' : 's' }} · newest first</span>
                </div>
                <div class="p-4">
                    <x-activity.timeline :events="$timeline" />
                </div>
            </section>
        </div>

        <aside class="lg:sticky lg:top-20 lg:self-start">
            <section class="panel">
                <div class="panel-header"><h2 class="section-title flex items-center gap-2"><x-lucide-square-pen class="size-4 text-slate" /> Record an update</h2></div>
                <div class="p-4">
                    <livewire:activities.record-update-form :instance="$instance" :key="'detail-form-'.$instance->id" />
                </div>
            </section>
        </aside>
    </div>
</div>
