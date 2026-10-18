<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportReportRequest;
use App\Reports\ReportRegistry;
use App\Services\AuditLogger;
use App\Support\CsvWriter;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(ExportReportRequest $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->filters();
        $report = ReportRegistry::make($filters->type);

        abort_unless($report->availableTo($request->user()), 403);

        $audit->record(
            event: 'report.exported',
            description: sprintf('Exported %s to CSV', strtolower($report->title())),
            subjectLabel: $report->title(),
            properties: ['filters' => $filters->describe()],
        );

        $preamble = [
            ['Npontu Operations Desk', $report->title()],
            ['Prepared', ops_datetime(now(), true).' '.ops_tz().' by '.$request->user()->name],
            ...collect($filters->describe())->map(fn ($value, $label) => [$label, $value])->values()->all(),
        ];

        $filename = sprintf('%s_%s_%s.csv', $report->key(), $filters->from->format('Ymd'), $filters->to->format('Ymd'));

        return CsvWriter::download($filename, $preamble, $report->csvHeadings($filters), function ($write) use ($report, $filters) {
            $results = $report->results($filters);

            if ($results instanceof Builder) {
                $results->chunk(500, function ($rows) use ($report, $filters, $write) {
                    $report->decorate($rows, $filters);
                    $rows->each(fn ($row) => $write($report->csvRow($row, $filters)));
                });

                return;
            }

            $results->each(fn ($row) => $write($report->csvRow($row, $filters)));
        });
    }
}
