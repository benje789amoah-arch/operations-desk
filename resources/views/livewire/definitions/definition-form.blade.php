@php use App\Enums\Priority; use App\Enums\Recurrence; @endphp

<div>
    <x-ui.page-header
        :crumbs="['Operations', 'Activity definitions' => route('definitions.index'), $definition ? $definition->code : 'New']"
        :title="$definition ? 'Edit '.$definition->code : 'New activity definition'"
        :description="$definition ? 'Changes apply to register entries created from now on. Existing entries and their history are not altered.' : 'Describe a check the desk performs and how often it should appear on the daily register.'" />

    <form wire:submit="save" class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_320px]" novalidate>
        <div class="space-y-4">
            {{-- 01 Identity --}}
            <section class="panel">
                <div class="panel-header">
                    <h2 class="section-title"><span class="mono mr-1.5 text-slate">01</span> Identity &amp; classification</h2>
                </div>
                <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-[12rem_1fr]">
                    <div>
                        <label class="field-label" for="code">Definition code</label>
                        <div class="relative">
                            <input id="code" type="text" value="{{ $nextCode }}" class="input pr-8 font-mono" disabled aria-describedby="code-hint">
                            <x-lucide-lock class="absolute right-2.5 top-2.5 size-4 text-slate" />
                        </div>
                        <p id="code-hint" class="field-hint">Assigned automatically and never changes.</p>
                    </div>
                    <div>
                        <label class="field-label" for="category">Category</label>
                        <select id="category" wire:model="form.category_id" class="input">
                            <option value="">Uncategorised</option>
                            @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                        </select>
                        <x-ui.error for="form.category_id" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="field-label" for="title">Activity title <span class="text-crimson" aria-hidden="true">*</span></label>
                        <input id="title" type="text" wire:model="form.title" maxlength="160" class="input" placeholder="e.g. Daily SMS count in comparison to SMS count from logs" @error('form.title') aria-invalid="true" aria-describedby="form-title-error" @enderror required>
                        <x-ui.error for="form.title" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="field-label" for="description">Procedure</label>
                        <textarea id="description" wire:model="form.description" rows="5" maxlength="5000" class="input" placeholder="Steps the person should follow, what counts as Done, and when to leave it Pending."></textarea>
                        <p class="field-hint">Shown beside the update form on the daily register.</p>
                        <x-ui.error for="form.description" />
                    </div>
                    <fieldset class="md:col-span-2">
                        <legend class="field-label">Priority</legend>
                        <div class="segmented">
                            @foreach (Priority::options() as $value => $label)
                                <label @class(['segment cursor-pointer has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-forest', 'segment-active' => $form->priority === $value])>
                                    <input type="radio" wire:model.live="form.priority" value="{{ $value }}" class="sr-only"> {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <p class="field-hint">Critical pending items are always listed under “Requires attention”.</p>
                    </fieldset>
                </div>
            </section>

            {{-- 02 Recurrence --}}
            <section class="panel">
                <div class="panel-header">
                    <h2 class="section-title"><span class="mono mr-1.5 text-slate">02</span> Recurrence &amp; schedule</h2>
                </div>
                <div class="space-y-4 p-4">
                    <fieldset>
                        <legend class="field-label">Recurrence</legend>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                            @foreach ([['daily', 'calendar'], ['weekdays', 'calendar-days'], ['weekly', 'repeat'], ['monthly', 'calendar-clock'], ['adhoc', 'calendar-plus']] as [$value, $icon])
                                <label @class([
                                    'flex cursor-pointer items-center justify-center gap-2 rounded-md border px-3 py-2.5 text-[13px] font-medium transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-forest',
                                    'border-forest bg-forest text-white' => $form->recurrence === $value,
                                    'border-hairline bg-surface hover:border-slate' => $form->recurrence !== $value,
                                ])>
                                    <input type="radio" wire:model.live="form.recurrence" value="{{ $value }}" class="sr-only">
                                    <x-dynamic-component :component="'lucide-'.$icon" class="size-4" /> {{ Recurrence::from($value)->label() }}
                                </label>
                            @endforeach
                        </div>
                        <x-ui.error for="form.recurrence" />
                    </fieldset>

                    @if ($form->recurrence === 'weekly')
                        <fieldset>
                            <legend class="field-label">Days of the week</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach (range(1, 7) as $day)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-md border border-hairline px-3 py-2 text-[13px] has-[:checked]:border-forest has-[:checked]:bg-forest-tint">
                                        <input type="checkbox" wire:model="form.weekdays" value="{{ $day }}" class="checkbox"> {{ Recurrence::weekdayName($day) }}
                                    </label>
                                @endforeach
                            </div>
                            <x-ui.error for="form.weekdays" />
                        </fieldset>
                    @elseif ($form->recurrence === 'monthly')
                        <div class="max-w-xs">
                            <label class="field-label" for="month-day">Day of the month</label>
                            <input id="month-day" type="number" min="1" max="31" wire:model="form.monthDay" class="input font-mono">
                            <p class="field-hint">Shorter months use their last day.</p>
                            <x-ui.error for="form.monthDay" />
                        </div>
                    @elseif ($form->recurrence === 'adhoc')
                        <p class="rounded-md border border-hairline bg-canvas p-3 text-[12.5px] text-slate">Ad-hoc activities are never created automatically. A supervisor schedules them for a specific date from the daily register.</p>
                    @endif

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="field-label" for="scheduled-time">Scheduled time ({{ ops_tz() }})</label>
                            <input id="scheduled-time" type="time" wire:model="form.scheduled_time" class="input font-mono">
                            <x-ui.error for="form.scheduled_time" />
                        </div>
                        <div>
                            <label class="field-label" for="due-time">Due by ({{ ops_tz() }})</label>
                            <input id="due-time" type="time" wire:model="form.due_time" class="input font-mono">
                            <p class="field-hint">Pending after this time counts as past due.</p>
                            <x-ui.error for="form.due_time" />
                        </div>
                        <div>
                            <label class="field-label" for="starts-on">First operational date</label>
                            <input id="starts-on" type="date" wire:model="form.starts_on" class="input font-mono" required>
                            <x-ui.error for="form.starts_on" />
                        </div>
                        <div>
                            <label class="field-label" for="ends-on">Last operational date</label>
                            <input id="ends-on" type="date" wire:model="form.ends_on" class="input font-mono">
                            <p class="field-hint">Optional.</p>
                            <x-ui.error for="form.ends_on" />
                        </div>
                    </div>

                    <div class="max-w-md">
                        <label class="field-label" for="assignee">Default responsible person</label>
                        <select id="assignee" wire:model="form.default_assignee_id" class="input">
                            <option value="">Unassigned — anyone on the desk</option>
                            @foreach ($people as $person)<option value="{{ $person->id }}">{{ $person->name }}{{ $person->designation ? ' · '.$person->designation : '' }}</option>@endforeach
                        </select>
                        <p class="field-hint">Copied onto each new register entry; supervisors can reassign individual days.</p>
                        <x-ui.error for="form.default_assignee_id" />
                    </div>

                    <div class="flex items-start gap-3 rounded-md border border-hairline bg-canvas p-3.5">
                        <x-lucide-shield-check class="mt-0.5 size-5 shrink-0 text-forest" />
                        <p class="text-[12.5px] leading-relaxed text-ink-soft">
                            <span class="font-medium text-ink">Duplicate-safe scheduling.</span>
                            The database allows one entry per definition per operational date, so scheduler retries or a second run on the same day never create duplicates or reset an entry's history.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
            <section class="panel p-4">
                <h2 class="section-title">Save definition</h2>
                @if ($definition)
                    <dl class="mt-3 space-y-1.5 text-[12.5px]">
                        <div class="flex justify-between gap-2"><dt class="text-slate">Created</dt><dd class="text-right">{{ ops_format($definition->created_at, 'd M Y') }} · {{ $definition->creator?->name }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-slate">Last changed</dt><dd class="text-right">{{ ops_format($definition->updated_at, 'd M Y H:i') }} · {{ $definition->editor?->name ?? '—' }}</dd></div>
                    </dl>
                @else
                    <p class="mt-1.5 text-[12.5px] text-slate">If the definition is due today it is added to today's register straight away.</p>
                @endif
                @if ($errors->any())
                    <p class="mt-3 flex items-start gap-1.5 rounded border border-crimson-line bg-crimson-tint p-2.5 text-[12.5px] text-crimson" role="alert">
                        <x-lucide-circle-alert class="mt-0.5 size-4 shrink-0" /> Please correct the highlighted fields.
                    </p>
                @endif
                <div class="mt-4 flex gap-2">
                    <button type="submit" class="btn btn-primary flex-1" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="flex items-center gap-2"><x-lucide-check class="size-4" /> {{ $definition ? 'Save changes' : 'Create definition' }}</span>
                        <span wire:loading wire:target="save" class="flex items-center gap-2"><x-lucide-loader-circle class="size-4 animate-spin" /> Saving…</span>
                    </button>
                    <a href="{{ $definition ? route('definitions.show', $definition) : route('definitions.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </section>
        </aside>
    </form>
</div>
