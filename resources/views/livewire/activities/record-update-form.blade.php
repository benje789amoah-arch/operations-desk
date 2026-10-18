@php
    $instance = $this->instance;
    $latest = $instance->latestUpdate;
    $user = auth()->user();
@endphp

<div class="space-y-4">
    {{-- Current state before any controls, so the person sees what they are changing. --}}
    <section class="rounded-md border border-hairline bg-canvas p-3.5" aria-label="Current state">
        <div class="flex items-center justify-between gap-2">
            <span class="eyebrow">Current state</span>
            <x-ui.status :state="$instance->wasReopened() ? 'reopened' : $instance->displayState()" />
        </div>
        @if ($latest)
            <p class="mt-2 text-[12.5px] text-slate">
                Last updated by <span class="font-medium text-ink">{{ $latest->updater_name }}</span>
                at <time class="num font-mono text-ink" datetime="{{ $latest->created_at->toIso8601String() }}">{{ ops_time($latest->created_at) }}</time>
                @unless (ops_is_today($latest->created_at)) on {{ ops_format($latest->created_at, 'd M') }} @endunless
            </p>
            <p class="mt-1.5 line-clamp-3 text-[13px] leading-relaxed text-ink">“{{ $latest->remark }}”</p>
        @else
            <p class="mt-2 text-[12.5px] text-slate">Nobody has updated this activity yet.</p>
        @endif
    </section>

    @if ($conflict)
        <div class="rounded-md border border-amber-line bg-amber-tint/60 p-3.5" role="alert">
            <p class="flex items-start gap-2 text-[13px] font-medium text-amber">
                <x-lucide-triangle-alert class="mt-0.5 size-4 shrink-0" /> {{ $conflict }}
            </p>
            <p class="mt-1.5 text-[12.5px] text-ink-soft">Your remark has not been saved and is still in the form below.</p>
            <button type="button" wire:click="acceptLatest" class="btn btn-secondary btn-sm mt-3">
                <x-lucide-check class="size-4" /> I have reviewed it — let me resubmit
            </button>
        </div>
    @endif

    @if (! $canRecord)
        <p class="flex items-start gap-2 rounded-md border border-hairline bg-surface p-3 text-[13px] text-slate">
            <x-lucide-lock class="mt-0.5 size-4 shrink-0" /> {{ $denial }}
        </p>
    @else
        <form wire:submit="save" class="space-y-4" novalidate>
            <fieldset>
                <legend class="field-label">New status <span class="font-normal text-slate">— required</span></legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ([
                        'pending' => ['Pending', 'Still outstanding or needs follow-up', 'circle-dot', 'has-[:checked]:border-amber-strong has-[:checked]:bg-amber-tint/60 has-[:checked]:ring-amber-strong'],
                        'done' => ['Done', 'Checked and complete', 'circle-check', 'has-[:checked]:border-forest has-[:checked]:bg-forest-tint has-[:checked]:ring-forest'],
                    ] as $value => [$label, $hint, $icon, $checked])
                        <label class="flex cursor-pointer items-start gap-2.5 rounded-md border border-hairline bg-surface p-3 transition-colors hover:border-slate has-[:checked]:ring-1 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-forest {{ $checked }}">
                            <input type="radio" name="status-{{ $instance->id }}" value="{{ $value }}" wire:model.live="status" class="mt-0.5 accent-forest" @if ($errors->has('status')) aria-invalid="true" aria-describedby="status-error" @endif>
                            <span>
                                <span class="flex items-center gap-1.5 text-[13.5px] font-medium"><x-dynamic-component :component="'lucide-'.$icon" class="size-4" /> {{ $label }}</span>
                                <span class="mt-0.5 block text-[12px] text-slate">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-ui.error for="status" />
            </fieldset>

            @if ($this->isReopening())
                <p class="flex items-start gap-2 rounded-md border border-amber-line bg-amber-tint/50 p-3 text-[12.5px] text-ink">
                    <x-lucide-rotate-ccw class="mt-0.5 size-4 shrink-0 text-amber" />
                    You are reopening a completed activity. The earlier completion stays in the history; explain why it is being reopened.
                </p>
            @endif

            <div x-data>
                <div class="flex items-baseline justify-between">
                    <label for="remark-{{ $instance->id }}" class="field-label">{{ $this->isReopening() ? 'Reason for reopening' : 'Remark' }} <span class="font-normal text-slate">— required</span></label>
                    <span class="num font-mono text-[11px] text-slate" aria-hidden="true"><span x-text="($wire.remark || '').length">0</span>/2000</span>
                </div>
                <textarea id="remark-{{ $instance->id }}" wire:model="remark" rows="{{ $compact ? 3 : 4 }}" maxlength="2000" class="input"
                          placeholder="e.g. SMS count from application records matched the count extracted from the logs."
                          @if ($errors->has('remark')) aria-invalid="true" aria-describedby="remark-error" @endif></textarea>
                <x-ui.error for="remark" />
                <p class="field-hint">Be specific: what you checked, what you found and anything the next person must do.</p>
            </div>

            <div>
                <label for="details-{{ $instance->id }}" class="field-label">Supporting details <span class="font-normal text-slate">— optional</span></label>
                <input id="details-{{ $instance->id }}" type="text" wire:model="supportingDetails" maxlength="500" class="input font-mono text-[12.5px]" placeholder="e.g. App count: 142,850 · Log count: 141,920 · Ticket NP-4912">
                <x-ui.error for="supportingDetails" />
            </div>

            <label x-show="$wire.status === 'pending'" x-cloak class="flex cursor-pointer items-start gap-2.5 rounded-md border border-hairline p-3 hover:bg-wash">
                <input type="checkbox" wire:model="followUp" class="checkbox mt-0.5">
                <span>
                    <span class="flex items-center gap-1.5 text-[13px] font-medium"><x-lucide-flag class="size-4 text-amber" /> Flag for follow-up at handover</span>
                    <span class="mt-0.5 block text-[12px] text-slate">Puts this activity at the top of the handover centre and the attention list.</span>
                </span>
            </label>

            {{-- Identity comes from the session, never from the form. --}}
            <div class="flex items-center gap-3 rounded-md border border-hairline bg-canvas p-3">
                <x-ui.avatar :name="$user->name" />
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="eyebrow text-[10px]">Recorded as</p>
                    <p class="mt-0.5 truncate text-[13px] font-medium">{{ $user->name }}</p>
                    <p class="truncate text-[11.5px] text-slate">{{ collect([$user->designation, $user->staff_number, $user->role->label()])->filter()->join(' · ') }}</p>
                </div>
                <p class="max-w-[9rem] text-right text-[11px] leading-snug text-slate">Time is stamped by the server when you save ({{ ops_tz() }})</p>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary flex-1 sm:flex-none" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save" class="flex items-center gap-2"><x-lucide-check class="size-4" /> Save update</span>
                    <span wire:loading wire:target="save" class="flex items-center gap-2"><x-lucide-loader-circle class="size-4 animate-spin" /> Saving…</span>
                </button>
                <button type="button" wire:click="cancel" class="btn btn-ghost">Cancel</button>
            </div>
        </form>
    @endif
</div>
