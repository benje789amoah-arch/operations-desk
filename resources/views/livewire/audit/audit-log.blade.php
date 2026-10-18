<div>
    <x-ui.page-header
        :crumbs="['Administration', 'System audit log']"
        title="System audit log"
        description="Restricted record of sign-ins, status changes, assignments, definition and personnel changes, handovers and exports. Separate from the remarks people write on activities.">
        <x-slot:actions>
            <button type="button" wire:click="verifyChain" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="verifyChain">
                <span wire:loading.remove wire:target="verifyChain" class="flex items-center gap-2"><x-lucide-shield-check class="size-4" /> Verify integrity</span>
                <span wire:loading wire:target="verifyChain" class="flex items-center gap-2"><x-lucide-loader-circle class="size-4 animate-spin" /> Verifying…</span>
            </button>
            <a href="{{ route('audit.export', array_filter(['from' => $from, 'to' => $to, 'category' => $category, 'actor' => $actor, 'search' => $search])) }}" class="btn btn-primary"><x-lucide-download class="size-4" /> Export CSV</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($verification)
        <div @class(['mb-4 flex items-start gap-3 rounded-lg border p-4', 'border-forest-line bg-forest-tint' => $verification['valid'], 'border-crimson-line bg-crimson-tint' => ! $verification['valid']]) role="status">
            <x-dynamic-component :component="$verification['valid'] ? 'lucide-shield-check' : 'lucide-shield-alert'" @class(['mt-0.5 size-5 shrink-0', 'text-forest' => $verification['valid'], 'text-crimson' => ! $verification['valid']]) />
            <div class="text-[13px]">
                @if ($verification['valid'])
                    <p class="font-medium text-forest">Audit chain intact — {{ number_format($verification['checked']) }} events verified at {{ $verification['at'] }}.</p>
                    <p class="mt-0.5 text-ink-soft">Every event's SHA-256 hash matches its content and links to the event before it, so no event has been edited or removed since it was written.</p>
                @else
                    <p class="font-medium text-crimson">Integrity check failed at event #{{ $verification['broken_at'] }} (after checking {{ $verification['checked'] }} events).</p>
                    <p class="mt-0.5 text-ink">{{ $verification['reason'] }} Treat records from this point as untrusted and investigate database access.</p>
                @endif
            </div>
        </div>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Events in view" :value="number_format($stats['total'])" icon="scroll-text" :hint="number_format($stats['all_time']).' recorded in total'" />
        <x-ui.stat label="Security flags" :value="$stats['security']" icon="shield-alert" :tone="$stats['security'] ? 'critical' : 'neutral'" hint="Failed sign-ins and lockouts" />
        <x-ui.stat label="Configuration changes" :value="$stats['changes']" icon="sliders-horizontal" hint="Definitions and personnel" />
        <x-ui.stat label="Exports" :value="$stats['exports']" icon="file-down" hint="Report and audit downloads" />
    </div>

    <section class="panel overflow-hidden">
        <div class="flex flex-col gap-2 border-b border-hairline p-2.5 xl:flex-row xl:items-center">
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-slate" />
                <label for="audit-search" class="sr-only">Search the audit log</label>
                <input id="audit-search" type="search" wire:model.live.debounce.400ms="search" class="input pl-8" placeholder="Search actor, event, reference, description or IP…">
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                <label for="audit-category" class="sr-only">Category</label>
                <select id="audit-category" wire:model.live="category" class="input sm:w-48">
                    <option value="">All categories</option>
                    @foreach ($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
                <label for="audit-actor" class="sr-only">Actor</label>
                <select id="audit-actor" wire:model.live="actor" class="input sm:w-44">
                    <option value="">Anyone</option>
                    <option value="system">System</option>
                    @foreach ($actors as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                </select>
                <label for="audit-from" class="sr-only">From</label>
                <input id="audit-from" type="date" wire:model.live="from" class="input font-mono sm:w-40">
                <label for="audit-to" class="sr-only">To</label>
                <input id="audit-to" type="date" wire:model.live="to" class="input font-mono sm:w-40">
            </div>
            <div class="segmented shrink-0">
                <button type="button" wire:click="preset(1)" class="segment">Today</button>
                <button type="button" wire:click="preset(7)" class="segment">7 days</button>
                <button type="button" wire:click="preset(30)" class="segment">30 days</button>
            </div>
        </div>

        @if ($events->isEmpty())
            <x-ui.empty icon="scroll-text" title="No audit events match these filters" />
        @else
            <div class="overflow-x-auto">
                <table class="table-ops">
                    <thead><tr><th class="w-40">Time ({{ ops_tz() }}) · ID</th><th>Actor</th><th>Event</th><th class="hidden md:table-cell">Subject</th><th class="hidden lg:table-cell">Change preview</th><th><span class="sr-only">Inspect</span></th></tr></thead>
                    <tbody>
                        @foreach ($events as $event)
                            @php($changes = $event->changes())
                            <tr wire:key="audit-{{ $event->id }}" @class(['bg-crimson-tint/40' => $event->tone() === 'critical'])>
                                <td class="whitespace-nowrap">
                                    <span class="num block font-mono text-[12px]">{{ ops_format($event->created_at, 'd M H:i:s') }}</span>
                                    <span class="font-mono text-[11px] text-slate">AUD-{{ str_pad($event->id, 6, '0', STR_PAD_LEFT) }}</span>
                                </td>
                                <td>
                                    <span class="flex items-center gap-2">
                                        <x-ui.avatar :name="$event->actor_name ?? 'System'" size="sm" />
                                        <span class="min-w-0"><span class="block text-[12.5px] font-medium">{{ $event->actor_name ?? 'System' }}</span><span class="font-mono text-[11px] text-slate">{{ $event->ip_address ?? '—' }}</span></span>
                                    </span>
                                </td>
                                <td>
                                    <span @class(['chip', 'chip-critical' => $event->tone() === 'critical', 'chip-pending' => $event->tone() === 'attention', 'chip-done' => $event->tone() === 'positive', 'chip-neutral' => $event->tone() === 'neutral'])>{{ strtoupper(str_replace(['.', '_'], ['·', ' '], $event->event)) }}</span>
                                    <p class="mt-1 max-w-xs text-[12px] text-ink-soft">{{ $event->description }}</p>
                                </td>
                                <td class="hidden font-mono text-[12px] md:table-cell">{{ $event->subject_label ?? '—' }}<span class="block text-[11px] text-slate">{{ $event->subject_type }}</span></td>
                                <td class="hidden max-w-[16rem] lg:table-cell">
                                    @if ($changes)
                                        <p class="truncate rounded bg-canvas px-2 py-1 font-mono text-[11px]">@foreach (array_slice($changes, 0, 2) as $c){{ $c['field'] }}: {{ is_scalar($c['before']) || $c['before'] === null ? var_export($c['before'], true) : '…' }} → {{ is_scalar($c['after']) || $c['after'] === null ? var_export($c['after'], true) : '…' }}@if (! $loop->last) | @endif @endforeach</p>
                                    @else
                                        <span class="text-[12px] text-slate">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <button type="button" wire:click="inspect({{ $event->id }})" class="btn btn-ghost btn-sm" aria-expanded="{{ $inspecting === $event->id ? 'true' : 'false' }}" aria-controls="inspect-{{ $event->id }}">
                                        <x-lucide-eye class="size-4" /> <span class="hidden sm:inline">Inspect</span>
                                    </button>
                                </td>
                            </tr>
                            @if ($inspecting === $event->id)
                                <tr id="inspect-{{ $event->id }}" wire:key="audit-detail-{{ $event->id }}" class="bg-canvas">
                                    <td colspan="6" class="!p-0">
                                        <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                            <div>
                                                <h3 class="eyebrow mb-2">State change</h3>
                                                @if ($changes)
                                                    <table class="w-full overflow-hidden rounded border border-hairline bg-surface text-[12px]">
                                                        <thead><tr class="bg-subtle text-left text-slate"><th class="px-2.5 py-1.5 font-medium">Field</th><th class="px-2.5 py-1.5 font-medium">Before</th><th class="px-2.5 py-1.5 font-medium">After</th></tr></thead>
                                                        <tbody>
                                                            @foreach ($changes as $c)
                                                                <tr class="border-t border-hairline align-top">
                                                                    <td class="px-2.5 py-1.5 font-mono">{{ $c['field'] }}</td>
                                                                    <td class="whitespace-pre-wrap break-words bg-crimson-tint/40 px-2.5 py-1.5 font-mono text-crimson">{{ is_scalar($c['before']) || $c['before'] === null ? var_export($c['before'], true) : json_encode($c['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</td>
                                                                    <td class="whitespace-pre-wrap break-words bg-forest-tint/60 px-2.5 py-1.5 font-mono text-forest">{{ is_scalar($c['after']) || $c['after'] === null ? var_export($c['after'], true) : json_encode($c['after'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @else
                                                    <p class="text-[12.5px] text-slate">This event records an action without a before/after state.</p>
                                                @endif
                                                @if ($extra = collect($event->properties ?? [])->except(['before', 'after'])->all())
                                                    <h3 class="eyebrow mb-1.5 mt-3">Context</h3>
                                                    <pre class="overflow-x-auto rounded border border-hairline bg-surface p-2.5 font-mono text-[11.5px]">{{ json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                                @endif
                                            </div>
                                            <dl class="space-y-2 text-[12px]">
                                                <div><dt class="eyebrow">Recorded (UTC)</dt><dd class="font-mono">{{ $event->created_at->utc()->format('Y-m-d H:i:s') }} UTC</dd></div>
                                                <div><dt class="eyebrow">User agent</dt><dd class="break-words font-mono text-[11px] text-ink-soft">{{ $event->user_agent ?? '—' }}</dd></div>
                                                <div><dt class="eyebrow">Event hash (SHA-256)</dt><dd class="break-all font-mono text-[11px]">{{ $event->hash }}</dd></div>
                                                <div><dt class="eyebrow">Previous hash</dt><dd class="break-all font-mono text-[11px] text-slate">{{ $event->previous_hash ?? 'Genesis — first event in the chain' }}</dd></div>
                                            </dl>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $events->links() }}
        @endif
    </section>

    <p class="mt-3 flex items-start gap-2 text-[12px] text-slate">
        <x-lucide-info class="mt-0.5 size-3.5 shrink-0" />
        Events cannot be edited or deleted from the application. The hash chain makes later database tampering detectable; it is not a substitute for restricting database access and keeping backups.
    </p>
</div>
