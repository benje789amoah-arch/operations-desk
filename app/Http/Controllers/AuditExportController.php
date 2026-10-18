<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Support\AuditLogQuery;
use App\Support\CsvWriter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditExportController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->only(['from', 'to', 'category', 'actor', 'search']);

        $audit->record('report.exported', 'Exported the system audit log to CSV', subjectLabel: 'System audit log', properties: ['filters' => array_filter($filters)]);

        return CsvWriter::download(
            'audit-log_'.now()->format('Ymd_His').'.csv',
            [['Npontu Operations Desk', 'System audit log'], ['Prepared', ops_datetime(now(), true).' '.ops_tz().' by '.$request->user()->name]],
            ['Event ID', 'Recorded at (UTC)', 'Actor', 'Event', 'Subject', 'Description', 'Changes', 'IP address', 'Hash', 'Previous hash'],
            function ($write) use ($filters) {
                AuditLogQuery::build($filters)->chunk(500, fn ($events) => $events->each(fn ($event) => $write([
                    $event->id,
                    $event->created_at->utc()->format('Y-m-d H:i:s'),
                    $event->actor_name,
                    $event->event,
                    $event->subject_label,
                    $event->description,
                    collect($event->changes())->map(fn ($c) => $c['field'].': '.json_encode($c['before']).' → '.json_encode($c['after']))->join('; '),
                    $event->ip_address,
                    $event->hash,
                    $event->previous_hash,
                ])));
            },
        );
    }
}
