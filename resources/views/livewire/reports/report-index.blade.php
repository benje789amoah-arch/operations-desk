<div>
    <x-ui.page-header
        :crumbs="['Insight', 'Reporting centre']"
        title="Reporting centre"
        description="Query the activity history for any period. Every figure comes from the stored records; each report explains exactly how it is calculated." />

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as ['report' => $report, 'available' => $available, 'icon' => $icon])
            <article @class(['panel flex flex-col p-4', 'opacity-60' => ! $available])>
                <div class="flex items-start gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-md bg-forest-tint text-forest"><x-dynamic-component :component="'lucide-'.$icon" class="size-5" /></span>
                    <div class="min-w-0">
                        <h2 class="section-title">{{ $report->title() }}</h2>
                        <p class="mt-1 text-[12.5px] leading-relaxed text-slate">{{ $report->description() }}</p>
                    </div>
                </div>
                <ul class="mt-3 flex-1 space-y-1 border-t border-hairline pt-3 text-[12px] text-ink-soft">
                    @foreach (array_slice($report->method(), 0, 2) as $rule)
                        <li class="flex gap-1.5"><x-lucide-info class="mt-0.5 size-3.5 shrink-0 text-slate" /> {{ $rule }}</li>
                    @endforeach
                </ul>
                <div class="mt-4 flex flex-wrap items-center gap-1.5">
                    @if ($available)
                        @foreach ($presets as $label => [$from, $to])
                            <a href="{{ route('reports.show', ['type' => $report->key(), 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" @class(['btn btn-sm', 'btn-primary' => $loop->index === 2, 'btn-secondary' => $loop->index !== 2])>{{ $label }}</a>
                        @endforeach
                    @else
                        <p class="flex items-center gap-1.5 text-[12px] text-slate"><x-lucide-lock class="size-3.5" /> Available to supervisors and administrators</p>
                    @endif
                </div>
            </article>
        @endforeach

        <article class="panel flex flex-col justify-between bg-canvas p-4">
            <div>
                <h2 class="section-title flex items-center gap-2"><x-lucide-file-spreadsheet class="size-4 text-slate" /> Exports</h2>
                <p class="mt-1 text-[12.5px] leading-relaxed text-slate">
                    @if ($canExport)
                        Every report can be exported to CSV with exactly the filters on screen. Exports are recorded in the audit log, and cells that could be read as spreadsheet formulas are escaped.
                    @else
                        CSV exports are available to supervisors and administrators. You can still run and print every report available to you.
                    @endif
                </p>
            </div>
            <p class="mt-3 text-[12px] text-slate">Times are shown in {{ config('operations.timezone') }} ({{ ops_tz() }}).</p>
        </article>
    </div>
</div>
