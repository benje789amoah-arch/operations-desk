{{-- Chronological audit trail for one activity instance (see App\Support\InstanceTimeline). --}}
@props(['events', 'newestFirst' => true])

@php
    $events = $newestFirst ? $events->reverse()->values() : $events;
@endphp

<ol {{ $attributes->class(['relative space-y-0']) }}>
    @foreach ($events as $event)
        @php
            $dot = match ($event['new_status'] ?? $event['type']) {
                'done' => 'bg-forest ring-forest-tint',
                'pending', 'reopened' => 'bg-amber-strong ring-amber-tint',
                'reassigned', 'scheduled' => 'bg-ink ring-subtle',
                default => 'bg-surface ring-hairline border border-slate',
            };
        @endphp
        <li class="relative flex gap-3 pb-5 last:pb-0">
            @unless ($loop->last)
                <span class="absolute left-[5px] top-4 h-[calc(100%-8px)] w-px bg-hairline" aria-hidden="true"></span>
            @endunless
            <span class="relative mt-1.5 size-[11px] shrink-0 rounded-full ring-4 {{ $dot }}" aria-hidden="true"></span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <time class="num font-mono text-[12px] font-medium text-ink" datetime="{{ $event['at']?->toIso8601String() }}" title="{{ ops_datetime($event['at'], true) }} {{ ops_tz() }}">
                        {{ ops_datetime($event['at']) }}
                    </time>
                    <span class="text-[12.5px] font-medium">{{ $event['label'] }}</span>
                    @if ($event['type'] === 'update')
                        <span class="flex items-center gap-1">
                            <x-ui.status :state="$event['previous_status']" />
                            <x-lucide-arrow-right class="size-3 text-slate" aria-label="to" />
                            <x-ui.status :state="$event['new_status']" />
                        </span>
                    @endif
                </div>

                <p class="mt-0.5 text-[12px] text-slate">
                    <span class="font-medium text-ink-soft">{{ $event['actor'] }}</span>@if ($event['actor_meta']) · {{ $event['actor_meta'] }}@endif
                    @if (! empty($event['id']))<span class="font-mono"> · UPD-{{ str_pad($event['id'], 6, '0', STR_PAD_LEFT) }}</span>@endif
                </p>

                @if (! empty($event['remark']))
                    <blockquote class="mt-2 whitespace-pre-line rounded border-l-2 border-hairline bg-canvas px-3 py-2 text-[13px] leading-relaxed text-ink">{{ $event['remark'] }}</blockquote>
                @endif
                @if (! empty($event['supporting_details']))
                    <p class="mt-1.5 rounded border border-dashed border-hairline px-2.5 py-1.5 font-mono text-[11.5px] text-ink-soft"><span class="text-slate">Details:</span> {{ $event['supporting_details'] }}</p>
                @endif
                @if (! empty($event['follow_up']))
                    <p class="mt-1.5 inline-flex items-center gap-1 text-[12px] font-medium text-amber"><x-lucide-flag class="size-3.5" /> Flagged for follow-up at handover</p>
                @endif
                @if (! empty($event['context']))
                    <p class="mt-0.5 text-[12px] text-slate">{{ $event['context'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
