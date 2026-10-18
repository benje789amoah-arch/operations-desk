<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Handover summary · {{ $date->format('d M Y') }} · Operations Desk</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; }
        .sheet { max-width: 1000px; margin: 0 auto; padding: 28px 24px 40px; }
        .sheet h2 { font-size: 14px; font-weight: 600; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #202522; }
        .sheet table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .sheet th { text-align: left; font-weight: 600; border-bottom: 1px solid #999; padding: 5px 6px; background: #f2f2f2; }
        .sheet td { border-bottom: 1px solid #ddd; padding: 6px; vertical-align: top; }
        .sheet tr { break-inside: avoid; }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="no-print mb-5 flex items-center justify-between rounded-md border border-hairline bg-canvas p-3">
            <p class="text-[13px] text-slate">Printable handover summary. Navigation and controls are not printed.</p>
            <div class="flex gap-2">
                <a href="{{ route('handover.index', array_filter(['date' => $date->format('Y-m-d'), 'shift' => $shift, 'person' => $person?->id])) }}" class="btn btn-secondary btn-sm">Back</a>
                <button type="button" onclick="window.print()" class="btn btn-primary btn-sm"><x-lucide-printer class="size-4" /> Print</button>
            </div>
        </div>

        <header class="flex items-start justify-between gap-6 border-b-2 border-ink pb-3">
            <div>
                <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-slate">Operations Desk · Applications Support</p>
                <h1 class="mt-1 text-[22px] font-semibold">Handover summary — {{ $date->format('l, j F Y') }}</h1>
                <p class="mt-1 text-[12.5px]">
                    Reporting period: operational date {{ $date->format('d M Y') }} (00:00–24:00 {{ ops_tz() }})
                    @if ($shift) · journal limited to {{ config("operations.shifts.$shift.label") }} ({{ config("operations.shifts.$shift.start") }}–{{ config("operations.shifts.$shift.end") }}) @endif
                    @if ($person) · filtered to {{ $person->name }} @endif
                </p>
            </div>
            <div class="text-right text-[12px]">
                <p>Prepared {{ ops_datetime(now(), true) }} {{ ops_tz() }}</p>
                <p>by {{ $preparedBy->name }}{{ $preparedBy->staff_number ? ' ('.$preparedBy->staff_number.')' : '' }}</p>
            </div>
        </header>

        <h2>Summary</h2>
        <table>
            <tr><th>Scheduled</th><th>Done</th><th>Pending (updated)</th><th>Not updated</th><th>Pending from earlier days</th><th>Outstanding to hand over</th><th>Updates recorded</th></tr>
            <tr class="num font-mono text-[14px]"><td>{{ $counts['total'] }}</td><td>{{ $counts['done'] }}</td><td>{{ $counts['pending'] }}</td><td>{{ $counts['not_updated'] }}</td><td>{{ $counts['carried_over'] }}</td><td><strong>{{ $outstanding->count() }}</strong></td><td>{{ $counts['updates'] }}</td></tr>
        </table>

        <h2>Outstanding items ({{ $outstanding->count() }})</h2>
        @if ($outstanding->isEmpty())
            <p class="text-[12.5px]">No outstanding activities.</p>
        @else
            <table>
                <thead><tr><th style="width: 15%">Reference</th><th style="width: 22%">Activity</th><th style="width: 12%">State</th><th>Latest remark</th><th style="width: 18%">Last updated</th></tr></thead>
                <tbody>
                    @foreach ($outstanding as $item)
                        <tr>
                            <td class="font-mono">{{ $item->reference() }}<br><span class="text-slate">{{ $item->definition->priority->label() }} priority</span></td>
                            <td>{{ $item->definition->title }}<br><span class="text-slate">Responsible: {{ $item->assignee?->name ?? 'Unassigned' }}</span></td>
                            <td>{{ $item->wasReopened() ? 'Reopened' : ($item->hasUpdates() ? 'Pending' : 'Not updated') }}@foreach ($item->attentionReasons($date) as $reason)<br><span class="text-slate">{{ $reason }}</span>@endforeach</td>
                            <td>{{ $item->latestUpdate?->remark ?? 'No update recorded.' }}@if ($item->latestUpdate?->supporting_details)<br><span class="font-mono text-slate">{{ $item->latestUpdate->supporting_details }}</span>@endif</td>
                            <td>@if ($item->latestUpdate){{ $item->latestUpdate->updater_name }}<br><span class="font-mono">{{ ops_datetime($item->latestUpdate->created_at, true) }}</span><br><span class="font-mono text-slate">UPD-{{ str_pad($item->latestUpdate->id, 6, '0', STR_PAD_LEFT) }}</span>@else — @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>Completed ({{ $completed->count() }})</h2>
        @if ($completed->isEmpty())
            <p class="text-[12.5px]">No activities completed for this date.</p>
        @else
            <table>
                <thead><tr><th style="width: 15%">Reference</th><th style="width: 28%">Activity</th><th>Remark</th><th style="width: 20%">Completed by</th></tr></thead>
                <tbody>
                    @foreach ($completed as $item)
                        <tr>
                            <td class="font-mono">{{ $item->reference() }}</td>
                            <td>{{ $item->definition->title }}</td>
                            <td>{{ $item->latestUpdate?->remark }}</td>
                            <td>{{ $item->latestUpdate?->updater_name }}<br><span class="font-mono">{{ ops_time($item->latestUpdate?->created_at, true) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>Update journal ({{ $journal->count() }})</h2>
        @if ($journal->isEmpty())
            <p class="text-[12.5px]">No activity updates were recorded for this period.</p>
        @else
            <table>
                <thead><tr><th style="width: 11%">Time</th><th style="width: 12%">Update ref</th><th style="width: 22%">Activity</th><th style="width: 14%">Change</th><th>Remark</th><th style="width: 15%">By</th></tr></thead>
                <tbody>
                    @foreach ($journal->sortBy('id') as $entry)
                        <tr>
                            <td class="font-mono">{{ ops_time($entry->created_at, true) }}</td>
                            <td class="font-mono">UPD-{{ str_pad($entry->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $entry->instance->reference() }}<br>{{ $entry->instance->definition->title }}</td>
                            <td>{{ $entry->previous_status?->label() ?? 'Not updated' }} → {{ $entry->new_status->label() }}</td>
                            <td>{{ $entry->remark }}</td>
                            <td>{{ $entry->updater_name }}@if ($entry->updater_staff_number)<br><span class="font-mono text-slate">{{ $entry->updater_staff_number }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>Shift handovers</h2>
        @forelse ($handovers as $handover)
            <div class="mb-3 text-[12.5px] print-avoid-break">
                <p><strong>{{ $handover->shiftLabel() }}</strong> — submitted by {{ $handover->outgoingUser->name }} at {{ ops_time($handover->submitted_at) }};
                    @if ($handover->isAcknowledged()) acknowledged by {{ $handover->incomingUser?->name }} at {{ ops_time($handover->acknowledged_at) }}. @else <em>not yet acknowledged.</em> @endif
                </p>
                <p class="mt-1 whitespace-pre-line border-l-2 border-rule pl-3">{{ $handover->outgoing_note }}</p>
            </div>
        @empty
            <p class="text-[12.5px]">No handover has been submitted for this date. This summary is not a record of a formal handover.</p>
        @endforelse

        <p class="mt-8 border-t border-hairline pt-2 text-[11px] text-slate">Statuses reflect the records at the time this summary was prepared. Times in {{ config('operations.timezone') }} ({{ ops_tz() }}).</p>
    </div>
</body>
</html>
